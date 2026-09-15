<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Thin proxy that forwards user/group/membership requests from the
 * frontend to the Auth Platform server-side.  The caller's JWT is
 * forwarded for /users and /groups; the configured API key is used
 * for /tenant/members endpoints.
 *
 * Privilege boundaries enforced here (in addition to catalog levels):
 * - Role changes (add/remove group) are cert-admin only.
 * - Status changes are cert-admin, or cert-staff limited to revoking
 *   (disabling) accounts whose only cert role is cert-user.
 * Staff hold the users.manage claim so the proxy can reach Auth; the
 * scoping above is what keeps that claim least-privilege in practice.
 * All mutations are audit-logged (actor resolved from JWT claims).
 */
class AuthProxyController extends Controller
{
    private string $authBaseUrl;
    private int $timeout;
    private string $apiKey;

    public function __construct(private readonly AuditLogger $auditLogger)
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

    public function showUser(Request $request, string $id): JsonResponse
    {
        return $this->proxyWithJwt('GET', "/api/v1/users/{$id}", $request);
    }

    public function updateUserStatus(Request $request, string $id): JsonResponse
    {
        $status = $request->input('status');

        if (!$this->isAdmin($request)) {
            // cert-staff: revoke-only (disable), Vericert User targets only.
            if (!in_array('cert-staff', $this->callerGroups($request), true)) {
                return response()->json(['message' => 'Only Vericert Admins may manage user status.'], 403);
            }
            if ($id === $this->callerSub($request)) {
                return response()->json(['message' => 'You cannot change your own status.'], 403);
            }
            if ($status !== 'disabled') {
                return response()->json(['message' => 'Staff may only revoke Vericert User accounts.'], 403);
            }
            $targetGroups = $this->fetchAuthUserGroups($id, $request);
            if ($targetGroups === null) {
                return response()->json(['message' => 'Unable to verify target account role.'], 403);
            }
            if (array_values($targetGroups) !== ['cert-user']) {
                return response()->json(['message' => 'Staff may only revoke Vericert User accounts.'], 403);
            }
        }

        $result = $this->proxyWithJwt('PATCH', "/api/v1/users/{$id}/status", $request);

        if ($result->getStatusCode() < 400) {
            $this->auditLogger->record('user.status_updated', 'api', 'user', $id, [
                'status' => $status,
            ]);
        }

        return $result;
    }

    // ── /users/{id}/groups (role membership) ────────────────────────────

    public function listUserGroups(Request $request, string $id): JsonResponse
    {
        return $this->proxyWithJwt('GET', "/api/v1/users/{$id}/groups", $request);
    }

    public function addUserGroup(Request $request, string $id): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'Only Vericert Admins may change roles.'], 403);
        }

        $result = $this->proxyWithJwt('POST', "/api/v1/users/{$id}/groups", $request);

        if ($result->getStatusCode() < 400) {
            $this->auditLogger->record('user.role_added', 'api', 'user', $id, [
                'group_id' => $request->input('group_id'),
            ]);
        }

        return $result;
    }

    public function removeUserGroup(Request $request, string $id, string $groupId): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'Only Vericert Admins may change roles.'], 403);
        }

        $result = $this->proxyWithJwt('DELETE', "/api/v1/users/{$id}/groups/{$groupId}", $request);

        if ($result->getStatusCode() < 400) {
            $this->auditLogger->record('user.role_removed', 'api', 'user', $id, [
                'group_id' => $groupId,
            ]);
        }

        return $result;
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
     * Group names of an Auth user, fetched server-side with the caller's JWT.
     * Returns null when the target cannot be verified (fail-closed: callers
     * treat null as deny). Upstream show requires users.view.
     *
     * @return string[]|null
     */
    private function fetchAuthUserGroups(string $id, Request $request): ?array
    {
        $http = Http::timeout($this->timeout)
            ->withHeaders(['Accept' => 'application/json']);

        $authHeader = $request->header('Authorization');
        if ($authHeader) {
            $http = $http->withHeaders(['Authorization' => $authHeader]);
        }

        try {
            $response = $http->get("{$this->authBaseUrl}/api/v1/users/{$id}");
        } catch (\Exception $e) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $groups = $response->json('groups');

        return is_array($groups) ? array_values($groups) : null;
    }

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
