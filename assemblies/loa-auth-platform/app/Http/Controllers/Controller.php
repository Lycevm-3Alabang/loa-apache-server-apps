<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\EncryptionService;
use App\Services\TenantService;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Returns the candidate URL when its origin belongs to an active tenant
     * (unified-auth-flow.md §0 D7: tenant rows are the only redirect
     * allowlist), fragment stripped; null otherwise. Shared by SSO and
     * password-reset redirect flows.
     */
    protected function safeRedirectUrl(mixed $candidate): ?string
    {
        if (!is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($candidate);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme']).'://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return app(TenantService::class)->resolveTenantByRedirectOrigin($origin) !== null
            ? explode('#', $candidate, 2)[0]
            : null;
    }

    /**
     * Stores the tenant handoff for the /redirect interstitial with the
     * normalized payload contract (unified-auth-flow.md §3): encrypted payload
     * when configured, raw token fragment otherwise.
     */
    protected function queueHandoff(
        Request $request,
        EncryptionService $encryption,
        User $user,
        string $url,
        array $tokens,
        Tenant $tenant,
    ): void {
        $payload = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
            ],
            'iat' => time(),
            'exp' => time() + $tokens['expires_in'],
        ];

        if ($encryption->isConfigured()) {
            $request->session()->put('redirect_payload', $encryption->encrypt($payload));
        } else {
            $request->session()->put(
                'redirect_fragment',
                http_build_query($tokens, '', '&', PHP_QUERY_RFC3986),
            );
        }

        $request->session()->put('redirect_url', $url);
    }
}
