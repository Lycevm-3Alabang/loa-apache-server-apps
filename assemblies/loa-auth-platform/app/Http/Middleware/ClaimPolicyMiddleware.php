<?php

namespace App\Http\Middleware;

use App\Models\RoutePolicy;
use App\Models\TenantAppEndpoint;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClaimPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $claims = $request->attributes->get('jwt_claims');

        if (!$claims) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userClaims = $claims['permissions'] ?? [];
        $userScopes = $claims['scopes'] ?? [];

        $method = $request->method();
        $path = $request->path();

        $routePolicies = RoutePolicy::where('method', strtoupper($method))
            ->where('path', $path)
            ->get();

        if (!$routePolicies->isEmpty()) {
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

        return $this->handleLevelBased($request, $claims, $userClaims, $next);
    }

    private function handleLevelBased(Request $request, array $claims, array $userClaims, Closure $next): Response
    {
        $tenantId = $claims['tenant']['id'] ?? null;
        $method = $request->method();
        $rawPath = $request->path();
        $path = '/' . $rawPath;

        $endpoint = TenantAppEndpoint::matchPath($method, $path, $tenantId);

        if (!$endpoint) {
            $allowedPaths = TenantAppEndpoint::whereIn('method', [$method, '*'])
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
                ->pluck('path')
                ->all();

            if (empty($allowedPaths)) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'no_catalog_entry',
            ], 403);
        }

        $requiredLevel = $endpoint->required_level;
        $requiredOrdinal = $this->levelOrdinal($requiredLevel);

        $grantedLevel = $this->resolveGrantedLevel($userClaims, $method, $path, $endpoint->path);

        if ($grantedLevel === null) {
            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'no_access',
                'required_level' => $requiredLevel,
                'effective_level' => 'deny',
            ], 403);
        }

        if ($grantedLevel === 'deny') {
            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'denied',
                'required_level' => $requiredLevel,
                'effective_level' => 'deny',
            ], 403);
        }

        $grantedOrdinal = $this->levelOrdinal($grantedLevel);

        if ($grantedOrdinal < $requiredOrdinal) {
            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'insufficient_level',
                'required_level' => $requiredLevel,
                'effective_level' => $grantedLevel,
            ], 403);
        }

        return $next($request);
    }

    private function resolveGrantedLevel(array $userClaims, string $method, string $requestPath, string $catalogPath): ?string
    {
        $levelEntries = array_filter($userClaims, fn ($entry) => is_string($entry) && preg_match('/^(read|write|admin|none|deny):/i', $entry));

        foreach ($levelEntries as $entry) {
            $parts = explode(':', 2, $entry);
            if (count($parts) !== 2) {
                continue;
            }

            $level = $parts[0];
            $entryPath = $parts[1];

            $entryMethod = null;
            if (str_starts_with($entryPath, '*')) {
                $entryMethod = '*';
                $entryPath = substr($entryPath, 1);
            }

            if ($this->pathMatches($entryPath, $requestPath)) {
                return $level;
            }
        }

        return null;
    }

    private function pathMatches(string $catalogPath, string $requestPath): bool
    {
        $catalogPath = $this->normalizePath($catalogPath);
        $requestPath = $this->normalizePath($requestPath);

        if ($catalogPath === $requestPath) {
            return true;
        }

        $pattern = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', preg_quote($catalogPath, '#'));
        $pattern = '#^' . $pattern . '$#';

        return (bool) preg_match($pattern, $requestPath);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function levelOrdinal(string $level): int
    {
        return match (strtolower($level)) {
            'read' => 1,
            'write' => 2,
            'admin' => 3,
            'deny' => -1,
            default => -1,
        };
    }

    private function passesFilter(string $filter, array $userScopes): bool
    {
        return match ($filter) {
            'all' => true,
            'author' => true,
            'scope' => !empty($userScopes),
            default => true,
        };
    }
}
