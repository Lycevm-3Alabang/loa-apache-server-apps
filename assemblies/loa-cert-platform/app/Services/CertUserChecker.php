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
     * Check if an email belongs to an existing member of the cert tenant.
     *
     * Fail-closed: returns true (assume registered) when the check cannot be performed.
     * This prevents showing the activate button to all recipients when the Auth Platform
     * is unreachable or the API key is not configured.
     */
    public function isRegistered(string $email): bool
    {
        if ($this->apiKey === '') {
            Log::warning('CertUserChecker: API key not configured, assuming registered');
            return true;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->authBaseUrl}/api/v1/tenant/members", [
                    'email' => $email,
                    'limit' => 1,
                ]);

            if ($response->failed()) {
                Log::warning('CertUserChecker: failed to check member, assuming registered', [
                    'email' => $email,
                    'status' => $response->status(),
                ]);
                return true;
            }

            $data = $response->json('data', []);
            return count($data) > 0;
        } catch (\Exception $e) {
            Log::warning('CertUserChecker: exception checking member, assuming registered', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    /**
     * Build the activation URL for a recipient who is not yet registered.
     */
    public function getActivateUrl(string $certificateNumber, string $email): string
    {
        $authUrl = config('auth-platform.base_url', 'https://auth.lyceumalabang.edu.ph');

        return $authUrl . '/set-password/cert?' . http_build_query([
            'cert' => $certificateNumber,
            'email' => $email,
        ]);
    }
}
