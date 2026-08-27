<?php

namespace App\Http\Middleware;

use App\Models\TenantApiKey;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($request);
        }

        $header = $request->header('X-Api-Key');

        if (!$header || !str_contains($header, ':')) {
            return response()->json(['message' => 'Invalid API key credentials'], 401);
        }

        [$key, $secret] = explode(':', $header, 2);

        if ($key === '' || $secret === '') {
            return response()->json(['message' => 'Invalid API key credentials'], 401);
        }

        $apiKey = TenantApiKey::where('key_hash', hash('sha256', $key))
            ->whereNull('revoked_at')
            ->first();

        if (!$apiKey || !hash_equals($apiKey->secret_hash, hash('sha256', $secret))) {
            return response()->json(['message' => 'Invalid API key credentials'], 401);
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json(['message' => 'Invalid API key credentials'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->attributes->set('tenant_id', $apiKey->tenant_id);
        $request->attributes->set('api_key_id', $apiKey->id);

        $response = $next($request);

        $this->addCorsHeaders($request, $response, $apiKey->tenant_id);

        return $response;
    }

    private function handlePreflight(Request $request): Response
    {
        $origin = $request->header('Origin');

        if (!$origin) {
            return response()->json(['message' => 'OK'], 200);
        }

        $tenant = Tenant::where('status', 'active')
            ->get()
            ->first(fn (Tenant $t) => in_array($origin, $t->effectiveRedirectOrigins(), true));

        if (!$tenant) {
            return response()->json(['message' => 'OK'], 200);
        }

        return response()->json(['message' => 'OK'], 200)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Api-Key, Authorization')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400');
    }

    private function addCorsHeaders(Request $request, Response $response, string $tenantId): void
    {
        $origin = $request->header('Origin');

        if (!$origin) {
            return;
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return;
        }

        $allowed = $tenant->effectiveRedirectOrigins();

        if (!in_array($origin, $allowed, true)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Api-Key, Authorization');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
    }
}
