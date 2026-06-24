<?php

declare(strict_types=1);

namespace App\Artha\Http\Controllers;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use App\Scopes\CurrentCompanyScope;
use App\Utilities\Currency\CurrencyAccessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
| Cross-module WRITE endpoints for the Artha Business OS. These let the
| Automations engine (and CRM event flows) create real accounting records
| when a CRM Opportunity is Won: a customer (Client) and a draft Invoice.
|
| Isolated in app/Artha so upstream erpsaas updates merge without conflict.
| Every action is idempotent where possible, wrapped in a transaction, and
| returns structured JSON (never a 500 the caller can't read).
*/
class ArthaWriteController
{
    public function createCustomer(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if (! $company instanceof Company) {
            return $this->noCompany();
        }

        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return response()->json(['message' => 'A customer name is required.'], 422);
        }

        $this->bindCompanyContext($company);

        try {
            $existing = Client::query()
                ->withoutGlobalScope(CurrentCompanyScope::class)
                ->where('clients.company_id', $company->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing instanceof Client) {
                return response()->json([
                    'company' => $this->companyPayload($company),
                    'created' => false,
                    'customer' => $this->clientPayload($existing),
                ]);
            }

            $email = $request->input('email');
            $contactName = $this->splitName((string) $request->input('contact_name', ''));

            $payload = [
                'name' => $name,
                'currency_code' => $request->input('currency_code') ?? CurrencyAccessor::getDefaultCurrency() ?? 'USD',
                'website' => $request->input('website'),
                'notes' => $this->originNote($request),
            ];

            if (is_string($email) && $email !== '') {
                $payload['primaryContact'] = [
                    'first_name' => $contactName['first'] ?? $name,
                    'last_name' => $contactName['last'] ?? '',
                    'email' => $email,
                    'phones' => $this->phones($request),
                ];
            }

            $client = Client::createWithRelations($payload);

            return response()->json([
                'company' => $this->companyPayload($company),
                'created' => true,
                'customer' => $this->clientPayload($client->fresh('primaryContact')),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not create customer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if (! $company instanceof Company) {
            return $this->noCompany();
        }

        $this->bindCompanyContext($company);

        $client = $this->resolveClient($request, $company);

        if (! $client instanceof Client) {
            return response()->json(['message' => 'A valid client_id or client_name is required.'], 422);
        }

        $amountCents = $this->amountCents($request);

        if ($amountCents <= 0) {
            return response()->json(['message' => 'A positive amount or amount_cents is required.'], 422);
        }

        try {
            $invoice = DB::transaction(function () use ($request, $company, $client, $amountCents): Invoice {
                $currency = $request->input('currency_code')
                    ?? $client->currency_code
                    ?? CurrencyAccessor::getDefaultCurrency()
                    ?? 'USD';

                $dueInDays = max(0, (int) $request->input('due_in_days', 30));

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'client_id' => $client->id,
                    'header' => 'Invoice',
                    'subheader' => 'Invoice',
                    'invoice_number' => 'ARTHA-PENDING-' . uniqid(),
                    'date' => today(),
                    'due_date' => today()->addDays($dueInDays),
                    'status' => InvoiceStatus::Draft,
                    'currency_code' => $currency,
                    'discount_method' => DocumentDiscountMethod::PerLineItem,
                    'discount_computation' => AdjustmentComputation::Percentage,
                    'discount_rate' => 0,
                    'terms' => $request->input('terms'),
                    'footer' => $this->originNote($request),
                ]);

                $invoice->lineItems()->create([
                    'company_id' => $company->id,
                    'offering_id' => null,
                    'description' => (string) $request->input('description', 'Services'),
                    'quantity' => 1,
                    'unit_price' => $amountCents,
                    'tax_total' => 0,
                    'discount_total' => 0,
                ]);

                $invoice->refresh();

                $subtotal = (int) $invoice->lineItems()->sum('subtotal');

                $invoice->update([
                    'subtotal' => $subtotal,
                    'tax_total' => 0,
                    'discount_total' => 0,
                    'total' => $subtotal,
                    'invoice_number' => 'INV-' . (DocumentDefault::getBaseNumber() + $invoice->id),
                ]);

                return $invoice;
            });

            return response()->json([
                'company' => $this->companyPayload($company),
                'created' => true,
                'invoice' => $this->invoicePayload($invoice->fresh('client')),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not create invoice.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveClient(Request $request, Company $company): ?Client
    {
        $clientId = $request->input('client_id');

        if (is_string($clientId) || is_int($clientId)) {
            $client = Client::query()
                ->withoutGlobalScope(CurrentCompanyScope::class)
                ->where('clients.company_id', $company->id)
                ->find($clientId);

            if ($client instanceof Client) {
                return $client;
            }
        }

        $name = trim((string) $request->input('client_name', ''));

        if ($name === '') {
            return null;
        }

        $existing = Client::query()
            ->withoutGlobalScope(CurrentCompanyScope::class)
            ->where('clients.company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing instanceof Client) {
            return $existing;
        }

        return Client::createWithRelations([
            'name' => $name,
            'currency_code' => $request->input('currency_code') ?? CurrencyAccessor::getDefaultCurrency() ?? 'USD',
            'notes' => $this->originNote($request),
        ]);
    }

    private function bindCompanyContext(Company $company): void
    {
        session(['current_company_id' => $company->id]);

        $owner = $company->owner;

        if ($owner !== null) {
            Auth::login($owner);
        }
    }

    private function amountCents(Request $request): int
    {
        if ($request->has('amount_cents')) {
            return (int) $request->input('amount_cents');
        }

        if ($request->has('amount')) {
            return (int) round(((float) $request->input('amount')) * 100);
        }

        return 0;
    }

    /**
     * @return array<string, string>
     */
    private function splitName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = (string) array_shift($parts);

        return [
            'first' => $first,
            'last' => implode(' ', $parts),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function phones(Request $request): array
    {
        $phone = $request->input('phone');

        return is_string($phone) && $phone !== '' ? [$phone] : [];
    }

    private function originNote(Request $request): ?string
    {
        $source = trim((string) $request->input('source', ''));

        return $source !== '' ? 'Created from Artha ' . $source : 'Created from the Artha Business OS.';
    }

    private function resolveCompany(Request $request): ?Company
    {
        $companyId = $request->input('company_id');

        if ((is_string($companyId) || is_int($companyId)) && (string) $companyId !== '') {
            return Company::query()->find($companyId);
        }

        return Company::query()->orderBy('id')->first();
    }

    private function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
        ];
    }

    private function clientPayload(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->primaryContact?->email,
            'currency_code' => $client->currency_code,
            'website' => $client->website,
        ];
    }

    private function invoicePayload(Invoice $invoice): array
    {
        $total = (int) $invoice->total;
        $paid = (int) $invoice->amount_paid;

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client' => $invoice->client?->name,
            'status' => $invoice->status?->value,
            'currency_code' => $invoice->currency_code,
            'total_cents' => $total,
            'amount_paid_cents' => $paid,
            'outstanding_cents' => max($total - $paid, 0),
            'due_date' => $invoice->due_date?->toDateString(),
        ];
    }

    private function noCompany(): JsonResponse
    {
        return response()->json(['message' => 'No company found.'], 404);
    }
}
