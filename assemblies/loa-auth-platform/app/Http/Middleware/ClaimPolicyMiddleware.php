<?php

namespace App\Http\Middleware;

use App\Models\RoutePolicy;
use App\Services\PermissionPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClaimPolicyMiddleware
{
    private PermissionPolicyService $policy;

    public function __construct(PermissionPolicyService $policy)
    {
        $this->policy = $policy;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $claims = $request->attributes->get('jwt_claims');

        if (!$claims) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userId = $claims['sub'];
        $tenantId = $claims['tenant']['id'] ?? null;

        $method = $request->method();
        $path = $request->path();

        $routePolicies = RoutePolicy::where('method', strtoupper($method))
            ->where('path', $path)
            ->get();

        if ($routePolicies->isEmpty()) {
            return $next($request);
        }

        $userClaims = $this->policy->resolveUserClaims($userId, $tenantId);
        $userScopes = $this->policy->resolveUserScopes($userId, $tenantId);

        foreach ($routePolicies as $routePolicy) {
            if (!in_array($routePolicy->claim_key, $userClaims, true)) {
                return response()->json([
                    'message' => 'Forbidden',
                    'reason' => 'missing_claim',
                    'claim' => $routePolicy->claim_key,
                ], 403);
            }

            if (!$this->passesFilter($routePolicy->filter, $userScopes)) {
                return response()->json([
                    'message' => 'Forbidden',
                    'reason' => 'filter_denied',
                    'filter' => $routePolicy->filter,
                ], 403);
            }
        }

        return $next($request);
    }

    private function passesFilter(string $filter, array $userScopes): bool
    {
        return match ($filter) {
            'none' => true,
            'all' => true,
            'author' => true,
            'scope' => !empty($userScopes),
            default => true,
        };
    }
}