<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stub middleware — pass-through until auth-integration.md §10 port from cert.
 *
 * Real implementation copies cert JwtMiddleware with config re-pointing:
 *   - tenant_slug → 'loa' (consult-platform)
 *   - cert_user attribute → consult_user
 *   - Error shapes verbatim (401 missing/invalid, 403 tenant_mismatch)
 */
class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Stub: allow all requests through until cert port.
        return $next($request);
    }
}
