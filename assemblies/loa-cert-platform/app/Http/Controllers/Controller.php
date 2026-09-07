<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
