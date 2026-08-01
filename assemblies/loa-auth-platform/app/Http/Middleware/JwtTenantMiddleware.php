<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $claims = $request->attributes->get('jwt_claims');

        if (!$claims) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $slug = env('TENANT_SLUG', config('auth-web.tenant_slug'));

        if ($slug === null || $slug === '') {
            return response()->json(['message' => 'Tenant not configured'], 500);
        }

        $claimTenant = $claims['tenant'] ?? null;

        if (!$claimTenant || ($claimTenant['slug'] ?? null) !== $slug) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant || !$tenant->isActive()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
