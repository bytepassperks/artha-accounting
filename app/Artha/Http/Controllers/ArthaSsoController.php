<?php

declare(strict_types=1);

namespace App\Artha\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyDefaultService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/*
| Single sign-on consumer for the Artha Business OS. The Artha CRM (identity
| provider) mints a short-lived HMAC token for a signed-in user and hands off
| here. We verify the signature + expiry, then log the matching user in —
| auto-provisioning a fully-set-up company on first arrival, mirroring a normal
| registration. Additive + feature-flagged: with no shared secret this is a
| no-op, so the existing password / Socialite login is never affected.
|
| Isolated in app/Artha so upstream erpsaas updates merge without conflict.
*/
class ArthaSsoController
{
    public function callback(Request $request): RedirectResponse
    {
        $secret = (string) config('artha.sso.secret', '');

        if (! (bool) config('artha.sso.enabled', false) || $secret === '') {
            return $this->failure('Artha single sign-on is not enabled.');
        }

        $claims = $this->verify((string) $request->query('artha_sso', ''), $secret);

        if ($claims === null) {
            return $this->failure('Your Artha sign-in link was invalid or has expired. Please log in.');
        }

        try {
            $user = $this->resolveUser($claims['email'], $claims['name']);
        } catch (Throwable) {
            return $this->failure('We could not sign you in from Artha. Please log in.');
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->to($this->homeUrl($user));
    }

    /**
     * @return array{email: string, name: string}|null
     */
    private function verify(string $token, string $secret): ?array
    {
        if (substr_count($token, '.') !== 1) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);

        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $secret, true));

        if (! hash_equals($expected, $encodedSignature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($decoded)) {
            return null;
        }

        $email = isset($decoded['email']) && is_string($decoded['email']) ? trim($decoded['email']) : '';
        $exp = isset($decoded['exp']) && is_numeric($decoded['exp']) ? (int) $decoded['exp'] : 0;
        $issuer = isset($decoded['iss']) && is_string($decoded['iss']) ? $decoded['iss'] : '';

        if ($email === '' || $issuer !== 'crm' || $exp < time()) {
            return null;
        }

        $name = isset($decoded['name']) && is_string($decoded['name']) && trim($decoded['name']) !== ''
            ? trim($decoded['name'])
            : Str::before($email, '@');

        return ['email' => $email, 'name' => $name];
    }

    private function resolveUser(string $email, string $name): User
    {
        $existing = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if ($existing instanceof User) {
            return $existing;
        }

        return DB::transaction(function () use ($email, $name): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
            ]);

            $user->markEmailAsVerified();

            $company = Company::forceCreate([
                'user_id' => $user->id,
                'name' => explode(' ', $name, 2)[0] . "'s Company",
                'personal_company' => true,
            ]);

            $user->ownedCompanies()->save($company);
            $user->switchCompany($company);

            app(CompanyDefaultService::class)->createCompanyDefaults($company, $user, 'USD', 'US', 'en');

            return $user;
        });
    }

    private function homeUrl(User $user): string
    {
        $panel = Filament::getPanel('company');
        $tenant = $user->getDefaultTenant($panel);

        return $tenant instanceof Model ? $panel->getUrl($tenant) : $panel->getUrl();
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()
            ->route('filament.company.auth.login')
            ->with('status', $message);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
