<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthLogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $cookieName = config('consult-platform.refresh_cookie', 'loa_connect_refresh');
        $refreshToken = $request->input('refresh_token')
            ?? $request->cookies->get($cookieName);

        if ($refreshToken) {
            $authBaseUrl = config('auth-platform.base_url', 'https://auth.lyceumalabang.edu.ph');
            $timeout = config('auth-platform.http_timeout', 5);

            try {
                Http::timeout($timeout)
                    ->post("{$authBaseUrl}/api/v1/auth/logout", [
                        'refresh_token' => $refreshToken,
                    ]);
            } catch (\Exception $e) {
                // Best effort — clear cookie regardless
            }
        }

        // Secure flag is env-driven (deviation from cert verbatim, approved):
        // plain-http local dev cannot store Secure cookies.
        return response()->json(null, 204)->withCookie(cookie(
            $cookieName,
            '',
            -1,
            '/api/v1/auth',
            null,
            (bool) config('consult-platform.refresh_cookie_secure', true),
            true,
            false,
            'lax'
        ));
    }
}
