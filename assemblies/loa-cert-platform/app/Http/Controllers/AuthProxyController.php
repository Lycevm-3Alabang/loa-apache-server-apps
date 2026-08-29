<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Thin proxy that forwards user/group/membership requests from the
 * frontend to the Auth Platform server-side.  The caller's JWT is
 * forwarded for /users and /groups; the configured API key is used
 * for /tenant/members endpoints.
 */
class AuthProxyController extends Controller
{
    private string $authBaseUrl;
    private int $timeout;
    private string $apiKey;

    public function __construct()
    {
        $this->authBaseUrl = config('auth-platform.base_url', 'https://auth.lyceumalabang.edu.ph');
        $this->timeout = config('auth-platform.http_timeout', 5);
        $this->apiKey = config('auth-platform.api_key', '');
    }

    // ── /users ───────────────────────────────────────────────────────────

    public function listUsers(Request $request): JsonResponse
    {
        return $this->proxyWithJwt('GET', '/api/v1/users', $request);
    }

    public function updateUserStatus(Request $request, string $id): JsonResponse
    {
        return $this->proxyWithJwt('PATCH', "/api/v1/users/{$id}/status", $request);
    }

    // ── /groups ──────────────────────────────────────────────────────────

    public function listGroups(Request $request): JsonResponse
    {
        return $this->proxyWithJwt('GET', '/api/v1/groups', $request);
    }

    // ── /tenant/members (API-key auth) ───────────────────────────────────

    public function listMembers(Request $request): JsonResponse
    {
        return $this->proxyWithApiKey('GET', '/api/v1/tenant/members', $request);
    }

    public function storeMember(Request $request): JsonResponse
    {
        return $this->proxyWithApiKey('POST', '/api/v1/tenant/members', $request);
    }

    public function destroyMember(Request $request, string $userId): JsonResponse
    {
        return $this->proxyWithApiKey('DELETE', "/api/v1/tenant/members/{$userId}", $request);
    }

    public function inviteMember(Request $request): JsonResponse
    {
        return $this->proxyWithApiKey('POST', '/api/v1/tenant/members/invite', $request);
    }

    // ── Internal helpers ─────────────────────────────────────────────────

    /**
     * Proxy with the caller's JWT forwarded (for /users, /groups).
     */
    private function proxyWithJwt(string $method, string $path, Request $request): JsonResponse
    {
        $upstream = "{$this->authBaseUrl}{$path}";

        $http = Http::timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
            ]);

        $authHeader = $request->header('Authorization');
        if ($authHeader) {
            $http = $http->withHeaders(['Authorization' => $authHeader]);
        }

        $response = match ($method) {
            'GET'    => $http->get($upstream, $request->query()),
            'PATCH'  => $http->patch($upstream, $request->input()),
            'POST'   => $http->post($upstream, $request->input()),
            'DELETE' => $http->delete($upstream),
            default  => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };

        return $this->relayResponse($response);
    }

    /**
     * Proxy with the configured API key (for /tenant/members).
     */
    private function proxyWithApiKey(string $method, string $path, Request $request): JsonResponse
    {
        if ($this->apiKey === '') {
            return response()->json(['message' => 'Auth API key not configured'], 500);
        }

        $upstream = "{$this->authBaseUrl}{$path}";

        $http = Http::timeout($this->timeout)
            ->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept'    => 'application/json',
            ]);

        $response = match ($method) {
            'GET'    => $http->get($upstream, $request->query()),
            'POST'   => $http->post($upstream, $request->input()),
            'DELETE' => $http->delete($upstream),
            default  => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };

        return $this->relayResponse($response);
    }

    private function relayResponse($response): JsonResponse
    {
        if ($response->failed()) {
            $body = $response->json();
            $status = $response->status();

            return response()->json(
                $body ?? ['message' => 'Upstream request failed'],
                $status >= 400 && $status < 600 ? $status : 502,
            );
        }

        $body = $response->json();

        return response()->json($body, $response->status());
    }
}
