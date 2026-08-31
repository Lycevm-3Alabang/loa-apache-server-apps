<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\EncryptionService;
use App\Services\JWTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthCallbackController extends Controller
{
    public function __construct(
        private EncryptionService $encryption,
        private JWTService $jwt,
        private AuditLogger $auditLogger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->input('payload');

        if (!$payload || !is_string($payload)) {
            return response()->json(['message' => 'Missing payload'], 400);
        }

        $decrypted = $this->encryption->decrypt($payload);

        if ($decrypted === null) {
            return response()->json(['message' => 'Invalid or tampered payload'], 400);
        }

        if (isset($decrypted['exp']) && $decrypted['exp'] < time()) {
            return response()->json(['message' => 'Stale payload'], 400);
        }

        $accessToken = $decrypted['access_token'] ?? null;

        if (!$accessToken) {
            return response()->json(['message' => 'Missing access_token in payload'], 400);
        }

        $claims = $this->jwt->validate($accessToken);

        if (!$claims) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        $tenantSlug = config('cert-platform.tenant_slug', 'loa-e-cert');

        if (($claims['tenant']['slug'] ?? '') !== $tenantSlug) {
            return response()->json([
                'message' => 'Forbidden',
                'reason' => 'tenant_mismatch',
            ], 403);
        }

        $refreshToken = $decrypted['refresh_token'] ?? null;

        if (!$refreshToken) {
            return response()->json(['message' => 'Missing refresh_token in payload'], 400);
        }

        $cookieName = config('cert-platform.refresh_cookie', 'loa_cert_refresh');
        $cookieTtl = config('cert-platform.refresh_cookie_ttl', 10080);

        $expiresAt = now()->addMinutes($cookieTtl);

        $response = response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $claims['exp'] - time(),
                'user' => [
                    'id' => $claims['sub'] ?? null,
                    'email' => $claims['email'] ?? null,
                    'name' => $claims['name'] ?? null,
                ],
                'tenant' => [
                    'id' => $claims['tenant']['id'] ?? null,
                    'slug' => $claims['tenant']['slug'] ?? null,
                ],
            ],
        ]);

        // Secure flag is env-driven: plain-http local dev cannot store
        // Secure cookies, which would silently kill the refresh flow.
        $response->withCookie(cookie(
            $cookieName,
            $refreshToken,
            $cookieTtl,
            '/api/v1/auth',
            null,
            (bool) config('cert-platform.refresh_cookie_secure', true),
            true,
            false,
            'lax'
        ));

        try {
            $this->auditLogger->record(
                'auth.sso_callback',
                'auth',
                'user',
                $claims['sub'] ?? null,
                [
                    'email' => $claims['email'] ?? null,
                    'tenant_slug' => $claims['tenant']['slug'] ?? null,
                ],
                $claims['sub'] ?? null,
                $claims['email'] ?? null,
            );
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed: ' . $e->getMessage());
        }

        return $response;
    }
}
