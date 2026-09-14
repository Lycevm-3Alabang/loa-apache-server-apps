<?php

namespace App\Http\Controllers;

use App\Models\PasswordSetToken;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\IdentityService;
use App\Services\TenantService;
use App\Services\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly TenantService $tenants,
        private readonly AuthorizationService $authorization,
    ) {
    }

    public function show(Request $request): View|string
    {
        $token = $request->query('token', '');

        if ($token === '') {
            return response('Invalid link.', 400);
        }

        $hashed = hash('sha256', $token);

        $record = PasswordSetToken::where('token', $hashed)
            ->whereNull('used_at')
            ->first();

        if (!$record || $record->isExpired()) {
            return response('This link has expired or has already been used.', 410);
        }

        return view('auth.set-password', [
            'token' => $token,
            'email' => $record->user->email,
        ]);
    }

    /**
     * Certificate activation — step 1: show confirmation page.
     *
     * GET /set-password/cert?cert={certificate_number}&email={recipient_email}
     *
     * Validates cert+email against Cert Platform, shows confirmation page.
     * No state mutation on GET.
     */
    public function showByCert(Request $request): View|RedirectResponse
    {
        $cert = $request->query('cert', '');
        $email = $request->query('email', '');

        if ($cert === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')->with('error', 'Invalid activation link.');
        }

        // 1. Validate cert + email against the Cert Platform
        if (!$this->validateCertificate($cert, $email)) {
            return redirect()->route('login')->with('error', 'Invalid or expired certificate.');
        }

        // 2. Check if user already exists
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            return redirect()->route('sso.login')
                ->with('status', 'You already have an account. Please sign in.');
        }

        // 3. Show confirmation page (no state mutation)
        return view('auth.cert-activate', [
            'certificateNumber' => $cert,
            'recipientName' => $this->getCertRecipientName($cert),
            'email' => $email,
        ]);
    }

    /**
     * Certificate activation — step 2: create user and show set-password form.
     *
     * POST /set-password/cert
     *
     * Creates user, adds to cert-user group, generates set-password token.
     */
    public function storeByCert(Request $request): View|RedirectResponse
    {
        $cert = $request->input('cert', '');
        $email = $request->input('email', '');

        if ($cert === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')->with('error', 'Invalid activation link.');
        }

        // 1. Validate cert + email against the Cert Platform
        if (!$this->validateCertificate($cert, $email)) {
            return redirect()->route('login')->with('error', 'Invalid or expired certificate.');
        }

        // 2. Check if user already exists (race condition guard)
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            return redirect()->route('sso.login')
                ->with('status', 'You already have an account. Please sign in.');
        }

        // 3. Create user + add to cert-user group (in transaction)
        try {
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($email) {
                $user = $this->identity->register($email, '', $this->generateName($email));
                $user->update(['status' => 'pending']);

                $tenant = \App\Models\Tenant::where('slug', config('cert-user.tenant_slug', 'loa-e-cert'))->first();

                if ($tenant) {
                    $this->tenants->addUserToTenant($user->id, $tenant->id);

                    $group = UserGroup::where('name', 'cert-user')
                        ->where('tenant_id', $tenant->id)
                        ->first();

                    if ($group) {
                        $this->authorization->addToGroup($user->id, $group->id);
                    }
                }

                // Generate set-password token
                $rawToken = bin2hex(random_bytes(32));
                $hashedToken = hash('sha256', $rawToken);

                PasswordSetToken::where('user_id', $user->id)->delete();

                PasswordSetToken::create([
                    'user_id' => $user->id,
                    'token' => $hashedToken,
                    'expires_at' => now()->addHours(48),
                ]);

                return [
                    'user' => $user,
                    'token' => $rawToken,
                ];
            });

            return view('auth.set-password', [
                'token' => $result['token'],
                'email' => $result['user']->email,
            ]);
        } catch (\Exception $e) {
            Log::warning('storeByCert: failed to create user', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Unable to create account. Please contact support.');
        }
    }

    public function set(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $hashed = hash('sha256', $request->input('token'));

        $record = PasswordSetToken::where('token', $hashed)
            ->whereNull('used_at')
            ->first();

        if (!$record || $record->isExpired()) {
            return back()->withErrors(['token' => 'This link has expired or has already been used.']);
        }

        $user = $record->user;

        $user->update([
            'password' => Hash::make($request->input('password')),
            'status' => 'active',
        ]);

        $record->update(['used_at' => now()]);

        return redirect()->route('login')->with('status', 'Your password has been set. You can now sign in.');
    }

    /**
     * Call the Cert Platform verify endpoint to validate cert + email.
     */
    private function validateCertificate(string $certificateNumber, string $email): bool
    {
        $certUrl = config('cert-platform.base_url', '');

        if ($certUrl === '') {
            Log::warning('validateCertificate: CERT_PLATFORM_URL not configured');
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->get("{$certUrl}/api/v1/verify/{$certificateNumber}");

            if ($response->failed()) {
                return false;
            }

            $data = $response->json('data', []);

            return ($data['recipient_email'] ?? '') === $email
                && ($data['status'] ?? '') === 'active';
        } catch (\Exception $e) {
            Log::warning('validateCertificate: request failed', [
                'cert' => $certificateNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Derive a display name from the email prefix when no name is provided.
     */
    private function generateName(string $email): string
    {
        $local = substr($email, 0, strpos($email, '@'));
        $parts = explode('.', $local);

        return ucfirst(str_replace(['-', '_'], ' ', $parts[0]));
    }

    /**
     * Get the recipient name from the Cert Platform verify endpoint.
     */
    private function getCertRecipientName(string $certificateNumber): string
    {
        $certUrl = config('cert-platform.base_url', '');

        if ($certUrl === '') {
            return '';
        }

        try {
            $response = Http::timeout(5)
                ->get("{$certUrl}/api/v1/verify/{$certificateNumber}");

            if ($response->failed()) {
                return '';
            }

            return $response->json('data.recipient_name', '');
        } catch (\Exception $e) {
            return '';
        }
    }
}
