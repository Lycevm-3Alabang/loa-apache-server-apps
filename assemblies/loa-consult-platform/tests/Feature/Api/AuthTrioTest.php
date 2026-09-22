<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTrioTest extends TestCase
{
    use RefreshDatabase;

    private function createAccessToken(array $overrides = []): string
    {
        $secret = config('jwt.secret');
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = array_merge([
            'sub' => 'user-123',
            'email' => 'juan@itmlyceumalabang.onmicrosoft.com',
            'name' => 'Juan Cruz',
            'type' => 'access',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa'],
            'groups' => ['STUDENT'],
            'permissions' => [],
            'iat' => time(),
            'exp' => time() + 900,
        ], $overrides);
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$encoded", $secret, true)), '+/', '-_'), '=');
        return "$header.$encoded.$sig";
    }

    private function encryptPayload(array $data): string
    {
        $raw = config('auth-platform.encryption_key');
        $key = base64_decode(substr($raw, 7));
        $nonce = random_bytes(12);
        $ciphertext = openssl_encrypt(json_encode($data), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        return rtrim(strtr(base64_encode($nonce . $tag . $ciphertext), '+/', '-_'), '=');
    }

    private function callbackPayload(array $overrides = []): array
    {
        return array_merge([
            'access_token' => $this->createAccessToken(),
            'refresh_token' => 'refresh-abc',
            'exp' => time() + 300,
        ], $overrides);
    }

    public function test_callback_rejects_missing_payload(): void
    {
        $this->postJson('/api/v1/auth/callback', [])
            ->assertStatus(400)
            ->assertJson(['message' => 'Missing payload']);
    }

    public function test_callback_rejects_tampered_payload(): void
    {
        $this->postJson('/api/v1/auth/callback', ['payload' => 'not-valid'])
            ->assertStatus(400)
            ->assertJson(['message' => 'Invalid or tampered payload']);
    }

    public function test_callback_rejects_stale_payload(): void
    {
        $payload = $this->encryptPayload($this->callbackPayload(['exp' => time() - 60]));

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])
            ->assertStatus(400)
            ->assertJson(['message' => 'Stale payload']);
    }

    public function test_callback_rejects_invalid_token(): void
    {
        $bad = $this->createAccessToken() . 'tampered';
        $payload = $this->encryptPayload($this->callbackPayload(['access_token' => $bad]));

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid access token']);
    }

    public function test_callback_rejects_tenant_mismatch(): void
    {
        $token = $this->createAccessToken(['tenant' => ['id' => 't-2', 'slug' => 'wrong']]);
        $payload = $this->encryptPayload($this->callbackPayload(['access_token' => $token]));

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden', 'reason' => 'tenant_mismatch']);
    }

    public function test_callback_rejects_missing_refresh_token(): void
    {
        $payload = $this->encryptPayload([
            'access_token' => $this->createAccessToken(),
            'exp' => time() + 300,
        ]);

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])
            ->assertStatus(400)
            ->assertJson(['message' => 'Missing refresh_token in payload']);
    }

    public function test_callback_success_upserts_student_and_sets_cookie(): void
    {
        $payload = $this->encryptPayload($this->callbackPayload());

        $response = $this->postJson('/api/v1/auth/callback', ['payload' => $payload]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => [
                'access_token', 'refresh_token', 'token_type', 'expires_in',
                'user' => ['id', 'email', 'name'],
                'tenant' => ['id', 'slug'],
            ]])
            ->assertPlainCookie('loa_connect_refresh', 'refresh-abc');

        $this->assertDatabaseHas('students', [
            'email' => 'juan@itmlyceumalabang.onmicrosoft.com',
            'name' => 'Juan Cruz',
        ]);
        $student = Student::where('email', 'juan@itmlyceumalabang.onmicrosoft.com')->first();
        $this->assertNotNull($student);
        $this->assertLessThanOrEqual(10, strlen($student->student_number));
        $this->assertDatabaseMissing('employees', [
            'email' => 'juan@itmlyceumalabang.onmicrosoft.com',
        ]);
    }

    public function test_callback_success_upserts_employee(): void
    {
        $token = $this->createAccessToken([
            'email' => 'ana@lyceumalabang.edu.ph',
            'name' => 'Ana Reyes',
            'groups' => ['FACULTY'],
        ]);
        $payload = $this->encryptPayload($this->callbackPayload(['access_token' => $token]));

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])->assertOk();

        $this->assertDatabaseHas('employees', [
            'email' => 'ana@lyceumalabang.edu.ph',
            'name' => 'Ana Reyes',
        ]);
        $this->assertDatabaseMissing('students', [
            'email' => 'ana@lyceumalabang.edu.ph',
        ]);
    }

    public function test_callback_refreshes_name_but_leaves_domain_attrs(): void
    {
        $student = new Student();
        $student->id = (string) Str::uuid();
        $student->name = 'Old Name';
        $student->email = 'juan@itmlyceumalabang.onmicrosoft.com';
        $student->student_number = '2021-00001';
        $student->is_active = false;
        $student->save();

        $payload = $this->encryptPayload($this->callbackPayload());

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])->assertOk();

        $this->assertDatabaseHas('students', [
            'email' => 'juan@itmlyceumalabang.onmicrosoft.com',
            'name' => 'Juan Cruz',
            'student_number' => '2021-00001',
            'is_active' => false,
        ]);
    }

    public function test_callback_unknown_domain_upserts_no_row(): void
    {
        $token = $this->createAccessToken([
            'email' => 'outsider@gmail.com',
            'name' => 'Outsider',
            'groups' => [],
        ]);
        $payload = $this->encryptPayload($this->callbackPayload(['access_token' => $token]));

        $this->postJson('/api/v1/auth/callback', ['payload' => $payload])->assertOk();

        $this->assertDatabaseMissing('students', ['email' => 'outsider@gmail.com']);
        $this->assertDatabaseMissing('employees', ['email' => 'outsider@gmail.com']);
    }

    public function test_refresh_rejects_missing_token(): void
    {
        $this->postJson('/api/v1/auth/refresh', [])
            ->assertStatus(401)
            ->assertJson(['message' => 'Missing refresh token']);
    }

    public function test_refresh_proxies_and_rotates_cookie(): void
    {
        Http::fake([
            '*' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 900,
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'old-refresh'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.access_token', 'new-access')
            ->assertPlainCookie('loa_connect_refresh', 'new-refresh');
    }

    public function test_refresh_upstream_failure_clears_cookie(): void
    {
        Http::fake(['*' => Http::response(['message' => 'bad'], 401)]);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'old-refresh'])
            ->assertStatus(401)
            ->assertCookieExpired('loa_connect_refresh');
    }

    public function test_refresh_incomplete_response_is_502(): void
    {
        Http::fake(['*' => Http::response(['access_token' => 'only-access'], 200)]);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'old-refresh'])
            ->assertStatus(502)
            ->assertCookieExpired('loa_connect_refresh');
    }

    public function test_logout_always_204_and_clears_cookie(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->postJson('/api/v1/auth/logout', ['refresh_token' => 'some-token'])
            ->assertNoContent()
            ->assertCookieExpired('loa_connect_refresh');
    }

    public function test_logout_without_token_still_204(): void
    {
        $this->postJson('/api/v1/auth/logout', [])
            ->assertNoContent()
            ->assertCookieExpired('loa_connect_refresh');
    }
}
