<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EndpointPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->method();
        $path = '/' . $request->path();

        $catalog = config('cert-endpoints.catalog', []);
        $endpoint = $this->matchEndpoint($method, $path, $catalog);

        if ($endpoint === null) {
            $publicPaths = config('cert-endpoints.public', []);
            foreach ($publicPaths as $publicPath) {
                if ($this->pathMatches($publicPath, $path)) {
                    return $next($request);
                }
            }

            $claims = $request->attributes->get('jwt_claims');
            if (!$claims) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'no_catalog_entry',
            ], 403);
        }

        $claims = $request->attributes->get('jwt_claims');

        if (!$claims) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $requiredLevel = $endpoint['required_level'];
        $requiredOrdinal = $this->levelOrdinal($requiredLevel);

        $permissions = $claims['permissions'] ?? [];
        $grantedLevel = $this->resolveGrantedLevel($permissions, $path, $endpoint['path']);

        if ($grantedLevel === null) {
            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'no_access',
                'required_level' => $requiredLevel,
                'effective_level' => 'none',
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

        $request->attributes->set('jwt_endpoint_level', $grantedLevel);

        return $next($request);
    }

    private function matchEndpoint(string $method, string $path, array $catalog): ?array
    {
        foreach ($catalog as $entry) {
            if (strtoupper($entry['method']) !== strtoupper($method)) {
                continue;
            }

            if ($this->pathMatches($entry['path'], $path)) {
                return $entry;
            }
        }

        return null;
    }

    private function resolveGrantedLevel(array $permissions, string $requestPath, string $catalogPath): ?string
    {
        $levelEntries = array_filter($permissions, fn ($entry) =>
            is_string($entry) && preg_match('/^(read|write|admin|none|deny):/i', $entry)
        );

        foreach ($levelEntries as $entry) {
            $parts = explode(':', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $level = $parts[0];
            $entryPath = $parts[1];

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

        $parts = preg_split('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $catalogPath);
        $escaped = array_map(fn ($p) => preg_quote($p, '#'), $parts);
        $pattern = '#^' . implode('([^/]+)', $escaped) . '$#';

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
            'write', 'admin' => 2,
            'deny' => -1,
            default => 0,
        };
    }
}
