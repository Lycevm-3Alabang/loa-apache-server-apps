<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        if (!$claims || ($claims['type'] ?? '') !== 'access') {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        $user = User::find($claims['sub']);

        if (!$user || !$user->isActive()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt_claims', $claims);
        $request->attributes->set('jwt_token', $token);

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
