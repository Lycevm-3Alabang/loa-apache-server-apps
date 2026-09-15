<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CertUserChecker
{
    private string $authBaseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->authBaseUrl = config('auth-platform.base_url', 'https://auth.lyceumalabang.edu.ph');
        $this->apiKey = config('auth-platform.api_key', '');
        $this->timeout = config('auth-platform.http_timeout', 5);
    }

    /**
     * Resolve the activation state for a recipient via the Auth invite endpoint.
     *
     * Returns ['isRegistered' => bool, 'activateUrl' => ?string].
     * Fail-closed: any failure (no key, transport error, unexpected shape)
     * yields isRegistered=true with no URL, so the mail stays View-only.
     */
    public function resolveActivation(string $name, string $email): array
    {
        $registered = ['isRegistered' => true, 'activateUrl' => null];

        if ($this->apiKey === '') {
            Log::warning('CertUserChecker: API key not configured, assuming registered');
            return $registered;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->post("{$this->authBaseUrl}/api/v1/tenant/members/invite", [
                    'name' => $name,
                    'email' => $email,
                    'groups' => ['cert-user'],
                    'notify' => false,
                ]);

            if ($response->failed()) {
                Log::warning('CertUserChecker: invite failed, assuming registered', [
                    'email' => $email,
                    'status' => $response->status(),
                ]);
                return $registered;
            }

            if ($response->json('status') === 'already_registered') {
                return $registered;
            }

            $token = $response->json('token');

            if (!is_string($token) || $token === '') {
                Log::warning('CertUserChecker: invite returned no token, assuming registered', [
                    'email' => $email,
                ]);
                return $registered;
            }

            return [
                'isRegistered' => false,
                'activateUrl' => $this->authBaseUrl . '/set-password?token=' . $token,
            ];
        } catch (\Exception $e) {
            Log::warning('CertUserChecker: exception inviting member, assuming registered', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return $registered;
        }
    }
}
