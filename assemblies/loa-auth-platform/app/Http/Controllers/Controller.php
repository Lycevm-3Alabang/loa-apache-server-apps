<?php

namespace App\Http\Controllers;

use App\Services\TenantService;

abstract class Controller
{
    /**
     * Returns the candidate URL when its origin is allowed (active tenant
     * redirect_origins or AUTH_ALLOWED_REDIRECTS), fragment stripped;
     * null otherwise. Shared by SSO and password-reset redirect flows.
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

        $allowed = in_array(rtrim(strtolower($origin), '/'), $this->allowedRedirectOrigins(), true)
            || app(TenantService::class)->resolveTenantByRedirectOrigin($origin) !== null;

        return $allowed ? explode('#', $candidate, 2)[0] : null;
    }

    private function allowedRedirectOrigins(): array
    {
        return array_map(
            static fn (string $url): string => rtrim(strtolower(trim($url)), '/'),
            (array) config('auth-web.allowed_redirects', []),
        );
    }
}
