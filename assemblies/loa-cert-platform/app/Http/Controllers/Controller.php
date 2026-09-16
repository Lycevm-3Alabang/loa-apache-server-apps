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
     * Returns 'active' | 'inactive' | 'not_found' | 'forbidden' | 'unreachable':
     * - 'active'     — user exists and status is active.
     * - 'inactive'   — user exists but status is disabled/locked/pending.
     * - 'not_found'  — Auth has no record of this user (404).
     * - 'forbidden'  — caller lacks users.view permission (403).
     * - 'unreachable'— transport failure or upstream 5xx (callers map to 502).
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

        if ($response->status() === 404) {
            return 'not_found';
        }

        if ($response->status() === 403) {
            return 'forbidden';
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

        return match ($status) {
            'active'    => null,
            'unreachable' => response()->json(['message' => "Auth service unavailable. {$action} was not saved."], 502),
            'not_found' => response()->json(['message' => "Your account was not found. Contact your administrator. {$action} was not saved."], 403),
            'forbidden' => response()->json(['message' => "Unable to verify account status. {$action} was not saved."], 403),
            default     => response()->json(['message' => "Your account is not active. {$action} was not saved."], 403),
        };
    }
}
