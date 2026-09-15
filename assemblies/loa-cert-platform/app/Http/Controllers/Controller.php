<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

abstract class Controller
{
    /**
     * JWT subject of the authenticated caller (set by JwtMiddleware).
     */
    protected function callerSub(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims');

        return $claims['sub'] ?? null;
    }

    /**
     * JWT groups of the authenticated caller (set by JwtMiddleware).
     */
    protected function callerGroups(Request $request): array
    {
        $certUser = $request->attributes->get('cert_user');

        return $certUser['groups'] ?? [];
    }

    /**
     * JWT email of the authenticated caller (set by JwtMiddleware).
     */
    protected function callerEmail(Request $request): ?string
    {
        $claims = $request->attributes->get('jwt_claims');

        return $claims['email'] ?? null;
    }

    /**
     * Whether the caller holds the cert-admin group.
     */
    protected function isAdmin(Request $request): bool
    {
        return in_array('cert-admin', $this->callerGroups($request), true);
    }

    /**
     * Author liveness check against the Auth Platform (spec event-visibility
     * §2 guard rail). JWTs validate locally, so a disabled or tenant-removed
     * user could otherwise keep writing until token expiry.
     *
     * Returns 'active' | 'inactive' | 'unreachable':
     * - 'inactive' covers resolved non-active status, unknown users, and
     *   upstream client errors (fail-closed deny).
     * - 'unreachable' covers transport failures and upstream 5xx (callers
     *   map this to 502). No caching — writes are infrequent, freshness wins.
     */
    protected function resolveAuthorStatus(Request $request, string $sub): string
    {
        $http = Http::timeout((int) config('auth-platform.http_timeout', 5))
            ->withHeaders(['Accept' => 'application/json']);

        $authHeader = $request->header('Authorization');
        if ($authHeader) {
            $http = $http->withHeaders(['Authorization' => $authHeader]);
        }

        $baseUrl = config('auth-platform.base_url', 'https://auth.lyceumalabang.edu.ph');

        try {
            $response = $http->get("{$baseUrl}/api/v1/users/{$sub}");
        } catch (\Exception $e) {
            return 'unreachable';
        }

        if ($response->serverError()) {
            return 'unreachable';
        }

        if ($response->failed()) {
            return 'inactive';
        }

        $user = $response->json();

        if (!is_array($user) || ($user['status'] ?? null) !== 'active') {
            return 'inactive';
        }

        return 'active';
    }

    /**
     * Enforce the author guard rail; returns an error response when the
     * caller must not author content, or null when the write may proceed.
     */
    protected function denyInactiveAuthor(Request $request, ?string $sub, string $action): ?\Illuminate\Http\JsonResponse
    {
        if (!$sub) {
            return response()->json(['message' => 'Unauthenticated author.'], 401);
        }

        $status = $this->resolveAuthorStatus($request, $sub);

        if ($status === 'unreachable') {
            return response()->json(['message' => "Auth service unavailable. {$action} was not saved."], 502);
        }

        if ($status !== 'active') {
            return response()->json(['message' => "Your account is not active. {$action} was not saved."], 403);
        }

        return null;
    }
}
