<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    private JWTService $jwt;

    public function __construct(JWTService $jwt)
    {
        $this->jwt = $jwt;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['message' => 'Missing bearer token'], 401);
        }

        $claims = $this->jwt->validate($token);

        if (!$claims) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        $tenantSlug = config('cert-platform.tenant_slug', 'loa-e-cert');

        if (($claims['tenant']['slug'] ?? '') !== $tenantSlug) {
            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'tenant_mismatch',
            ], 403);
        }

        $certUser = [
            'sub' => $claims['sub'] ?? null,
            'email' => $claims['email'] ?? null,
            'name' => $claims['name'] ?? null,
            'tenant' => $claims['tenant'] ?? null,
            'groups' => $claims['groups'] ?? [],
            'permissions' => $claims['permissions'] ?? [],
        ];

        $request->attributes->set('jwt_claims', $claims);
        $request->attributes->set('jwt_token', $token);
        $request->attributes->set('cert_user', $certUser);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header || !preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
