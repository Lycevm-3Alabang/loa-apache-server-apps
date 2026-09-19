<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stub middleware — pass-through until auth-integration.md §10 port from cert.
 *
 * Real implementation copies cert EndpointPolicyMiddleware with config re-pointing:
 *   - catalog → consult-endpoints.php (generated from api-endpoints.md §5)
 *   - request-attr names confirmed at port time
 *   - deny=-1, read=1, write=2, admin=3 ordinals
 */
class EndpointPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Stub: allow all requests through until cert port.
        return $next($request);
    }
}
