<?php

declare(strict_types=1);

namespace App\Artha\Http\Controllers;

use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Company;
use App\Scopes\CurrentCompanyScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArthaApiController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'app' => 'Artha Accounting',
            'key' => 'accounting',
            'status' => 'ok',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if (! $company instanceof Company) {
            return $this->noCompany();
        }

        $clients = Client::query()
            ->withoutGlobalScope(CurrentCompanyScope::class)
            ->where('clients.company_id', $company->id)
            ->with(['primaryContact' => fn ($query) => $query->withoutGlobalScope(CurrentCompanyScope::class)])
            ->orderBy('name')
            ->limit($this->limit($request))
            ->get();

        $data = $clients->map(static fn (Client $client): array => [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->primaryContact?->email,
            'currency_code' => $client->currency_code,
            'website' => $client->website,
        ])->all();

        return response()->json([
            'company' => $this->companyPayload($company),
            'count' => count($data),
            'data' => $data,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if (! $company instanceof Company) {
            return $this->noCompany();
        }

        $query = Invoice::query()
            ->withoutGlobalScope(CurrentCompanyScope::class)
            ->where('invoices.company_id', $company->id)
            ->with(['client' => fn ($query) => $query->withoutGlobalScope(CurrentCompanyScope::class)])
            ->orderByDesc('date');

        if ($request->boolean('unpaid')) {
            $query->whereIn('status', InvoiceStatus::unpaidStatuses());
        }

        $invoices = $query->limit($this->limit($request))->get();

        $data = $invoices->map(function (Invoice $invoice): array {
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
        })->all();

        return response()->json([
            'company' => $this->companyPayload($company),
            'count' => count($data),
            'data' => $data,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if (! $company instanceof Company) {
            return $this->noCompany();
        }

        $invoices = Invoice::query()
            ->withoutGlobalScope(CurrentCompanyScope::class)
            ->where('invoices.company_id', $company->id);

        $unpaid = (clone $invoices)->whereIn('status', InvoiceStatus::unpaidStatuses());

        $outstanding = (clone $unpaid)->get()
            ->sum(static fn (Invoice $invoice): int => max((int) $invoice->total - (int) $invoice->amount_paid, 0));

        $clientCount = Client::query()
            ->withoutGlobalScope(CurrentCompanyScope::class)
            ->where('clients.company_id', $company->id)
            ->count();

        return response()->json([
            'company' => $this->companyPayload($company),
            'customers' => $clientCount,
            'invoices_total' => (clone $invoices)->count(),
            'invoices_unpaid' => $unpaid->count(),
            'outstanding_cents' => $outstanding,
        ]);
    }

    private function resolveCompany(Request $request): ?Company
    {
        $companyId = $request->query('company_id');

        if (is_string($companyId) && $companyId !== '') {
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

    private function limit(Request $request): int
    {
        $limit = (int) $request->query('limit', '50');

        return max(1, min($limit, 200));
    }

    private function noCompany(): JsonResponse
    {
        return response()->json(['message' => 'No company found.'], 404);
    }
}
