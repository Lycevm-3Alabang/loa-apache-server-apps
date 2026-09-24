<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\EncryptionService;
use App\Services\JWTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $tenantSlug = config('consult-platform.tenant_slug', 'loa-consultation');

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

        $this->upsertLocalUser($claims);

        $cookieName = config('consult-platform.refresh_cookie', 'loa_connect_refresh');
        $cookieTtl = config('consult-platform.refresh_cookie_ttl', 10080);

        $response = response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
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
            (bool) config('consult-platform.refresh_cookie_secure', true),
            true,
            false,
            'lax'
        ));

        try {
            $this->auditLogger->fromClaims('auth.sso_callback', $claims, [
                'email' => $claims['email'] ?? null,
                'tenant_slug' => $claims['tenant']['slug'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return $response;
    }

    /**
     * First-class upsert by email (auth-integration.md §7, data-model.md
     * §3.1.1–§3.1.2). Email domain routes the table; only `name` refreshes —
     * domain attributes (student_number, course_id, employee_number,
     * department_id, is_active) stay local. Unknown domain → no row.
     */
    private function upsertLocalUser(array $claims): void
    {
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $name = $claims['name'] ?? null;

        if ($email === '') {
            return;
        }

        if (str_ends_with($email, '@itmlyceumalabang.onmicrosoft.com')) {
            $student = Student::firstOrNew(['email' => $email]);
            if (!$student->exists) {
                $student->id = (string) Str::uuid();
                // Placeholder until import assigns the academic identifier.
                // Max 10 chars (student_number domain rule).
                $student->student_number = 'SSO-' . substr($student->id, 0, 6);
            }
            if ($name !== null) {
                $student->name = $name;
            } elseif (!$student->exists) {
                $student->name = $email;
            }
            $student->save();
        } elseif (str_ends_with($email, '@lyceumalabang.edu.ph')) {
            $employee = Employee::firstOrNew(['email' => $email]);
            if (!$employee->exists) {
                $employee->id = (string) Str::uuid();
            }
            if ($name !== null) {
                $employee->name = $name;
            } elseif (!$employee->exists) {
                $employee->name = $email;
            }
            $employee->save();
        }
    }
}
