<?php

namespace App\Http\Controllers;

use App\Services\TenantService;

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
}
