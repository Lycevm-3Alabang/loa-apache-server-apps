<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            if ($response->failed()) {
                $cookieTtl = config('cert-platform.refresh_cookie_ttl', 10080);
                $clearResponse = response()->json(['message' => 'Invalid refresh token'], 401);
                $clearResponse->cookies->queue(
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
                return $clearResponse;
            }

            $data = $response->json();
            $newRefreshToken = $data['refresh_token'] ?? $refreshToken;
            $accessToken = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 900;

            $cookieTtl = config('cert-platform.refresh_cookie_ttl', 10080);

            $successResponse = response()->json([
                'status' => 'success',
                'data' => [
                    'access_token' => $accessToken,
                    'token_type' => 'Bearer',
                    'expires_in' => $expiresIn,
                ],
            ]);

            $successResponse->cookies->queue(
                $cookieName,
                $newRefreshToken,
                $cookieTtl,
                '/api/v1/auth',
                null,
                true,
                true,
                false,
                'lax'
            );

            return $successResponse;
        } catch (\Exception $e) {
            $clearResponse = response()->json(['message' => 'Auth service unavailable'], 502);
            $clearResponse->cookies->queue(
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
            return $clearResponse;
        }
    }
}
