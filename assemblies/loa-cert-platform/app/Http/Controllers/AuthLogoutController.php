<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;

class AuthLogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $cookieName = config('cert-platform.refresh_cookie', 'loa_cert_refresh');
        $refreshToken = $request->cookies->get($cookieName);

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

        Cookie::queue(
            $cookieName,
            '',
            -1,
            '/api/v1/auth',
            null,
            true,
            true,
            false,
            'lax'
        );

        return response()->json(null, 204);
    }
}
