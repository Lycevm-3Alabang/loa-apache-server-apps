<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        $claims = $request->attributes->get('jwt_claims');

        if (!$claims) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $permissions = $claims['permissions'] ?? [];

        if (!in_array($permissionKey, $permissions, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
