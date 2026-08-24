<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;

class AuthRefreshController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $cookieName = config('cert-platform.refresh_cookie', 'loa_cert_refresh');
        $refreshToken = $request->cookies->get($cookieName);

        if (!$refreshToken) {
            return response()->json(['message' => 'Missing refresh token'], 401);
        }

        $authBaseUrl = config('auth-platform.base_url', 'https://auth.lyceumalabang.edu.ph');
        $timeout = config('auth-platform.http_timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->post("{$authBaseUrl}/api/v1/auth/refresh", [
                    'refresh_token' => $refreshToken,
                ]);
        } catch (\Exception $e) {
            $this->clearRefreshCookie();

            return response()->json(['message' => 'Auth service unavailable'], 502);
        }

        if ($response->failed()) {
            $this->clearRefreshCookie();

            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        $data = $response->json();
        $newRefreshToken = $data['refresh_token'] ?? $refreshToken;
        $accessToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 900;

        Cookie::queue(
            $cookieName,
            $newRefreshToken,
            (int) config('cert-platform.refresh_cookie_ttl', 10080),
            '/api/v1/auth',
            null,
            true,
            true,
            false,
            'lax'
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
            ],
        ]);
    }

    private function clearRefreshCookie(): void
    {
        Cookie::queue(
            config('cert-platform.refresh_cookie', 'loa_cert_refresh'),
            '',
            -1,
            '/api/v1/auth',
            null,
            true,
            true,
            false,
            'lax'
        );
    }
}
