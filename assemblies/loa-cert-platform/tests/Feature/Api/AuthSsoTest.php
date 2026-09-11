<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSsoTest extends TestCase
{
    use RefreshDatabase;

    private string $hexKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'loa',
        ]);

        config(['cert-platform.tenant_slug' => 'loa-e-cert']);
        config(['cert-platform.refresh_cookie' => 'loa_cert_refresh']);
        config(['cert-platform.refresh_cookie_ttl' => 10080]);
        config(['cert-platform.refresh_cookie_secure' => false]);
        config(['cert-platform.organization_id' => $this->organization->id]);
        config(['auth-platform.encryption_key' => $this->hexKey]);
        config(['jwt.secret' => 'test-secret-key-for-auth-sso-tests']);
        config(['auth-platform.base_url' => 'https://auth.example.com']);
        config(['auth-platform.http_timeout' => 5]);
    }

    // ─── POST /api/v1/auth/callback ───────────────────────────────

    public function test_callback_missing_payload_returns_400(): void
    {
        $response = $this->postJson('/api/v1/auth/callback', []);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Missing payload']);
    }

    public function test_callback_non_string_payload_returns_400(): void
    {
        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => 12345,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Missing payload']);
    }

    public function test_callback_tampered_payload_returns_400(): void
    {
        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => 'tampered-garbage-data',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Invalid or tampered payload']);
    }

    public function test_callback_expired_payload_returns_400(): void
    {
        $accessToken = $this->createJwt([
            'exp' => time() - 3600,
        ]);

        $payload = $this->encryptPayload([
            'access_token' => $accessToken,
            'refresh_token' => 'valid-refresh-token',
            'exp' => time() - 3600,
        ]);

        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Stale payload']);
    }

    public function test_callback_missing_access_token_returns_400(): void
    {
        $payload = $this->encryptPayload([
            'refresh_token' => 'some-refresh-token',
        ]);

        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Missing access_token in payload']);
    }

    public function test_callback_invalid_jwt_returns_401(): void
    {
        $payload = $this->encryptPayload([
            'access_token' => 'not.a.valid.jwt',
            'refresh_token' => 'some-refresh-token',
        ]);

        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid access token']);
    }

    public function test_callback_tenant_mismatch_returns_403(): void
    {
        $accessToken = $this->createJwt([
            'tenant' => ['id' => 'wrong', 'slug' => 'wrong-tenant'],
        ]);

        $payload = $this->encryptPayload([
            'access_token' => $accessToken,
            'refresh_token' => 'some-refresh-token',
        ]);

        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
                'reason' => 'tenant_mismatch',
            ]);
    }

    public function test_callback_missing_refresh_token_returns_400(): void
    {
        $accessToken = $this->createJwt([
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa-e-cert'],
        ]);

        $payload = $this->encryptPayload([
            'access_token' => $accessToken,
        ]);

        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Missing refresh_token in payload']);
    }

    public function test_callback_success_returns_tokens_and_sets_cookie(): void
    {
        $accessToken = $this->createJwt([
            'sub' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa-e-cert'],
        ]);

        $payload = $this->encryptPayload([
            'access_token' => $accessToken,
            'refresh_token' => 'my-refresh-token',
        ]);

        $response = $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'access_token' => $accessToken,
                    'refresh_token' => 'my-refresh-token',
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => 'user-123',
                        'email' => 'test@example.com',
                        'name' => 'Test User',
                    ],
                    'tenant' => [
                        'id' => 'tenant-1',
                        'slug' => 'loa-e-cert',
                    ],
                ],
            ]);

        $this->assertRawCookie($response, 'loa_cert_refresh', 'my-refresh-token');
    }

    public function test_callback_success_creates_audit_log(): void
    {
        $accessToken = $this->createJwt([
            'sub' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa-e-cert'],
        ]);

        $payload = $this->encryptPayload([
            'access_token' => $accessToken,
            'refresh_token' => 'my-refresh-token',
        ]);

        $this->postJson('/api/v1/auth/callback', [
            'payload' => $payload,
        ]);

        $audit = AuditLog::where('action', 'auth.sso_callback')
            ->where('user_id', 'user-123')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('user-123', $audit->entity_id);
        $this->assertEquals('auth', $audit->source);
        $this->assertEquals([
            'email' => 'test@example.com',
            'tenant_slug' => 'loa-e-cert',
        ], $audit->details);
    }

    // ─── POST /api/v1/auth/refresh ────────────────────────────────

    public function test_refresh_missing_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', []);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Missing refresh token']);
    }

    public function test_refresh_from_cookie_when_body_missing(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 900,
            ], 200),
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/auth/refresh',
            [],
            ['loa_cert_refresh' => 'cookie-refresh-token'],
            [],
            ['CONTENT_TYPE' => 'application/json'],
        );

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://auth.example.com/api/v1/auth/refresh'
                && json_decode($request->body(), true)['refresh_token'] === 'cookie-refresh-token';
        });
    }

    public function test_refresh_success_returns_tokens_and_sets_cookie(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 900,
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'old-refresh-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'access_token' => 'new-access-token',
                    'refresh_token' => 'new-refresh-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 900,
                ],
            ]);

        $this->assertRawCookie($response, 'loa_cert_refresh', 'new-refresh-token');
    }

    public function test_refresh_forwards_token_to_auth_platform(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 900,
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'my-refresh-token',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://auth.example.com/api/v1/auth/refresh'
                && json_decode($request->body(), true)['refresh_token'] === 'my-refresh-token';
        });
    }

    public function test_refresh_invalid_token_returns_401_and_clears_cookie(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => Http::response([
                'message' => 'Invalid token',
            ], 401),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'bad-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid refresh token']);

        $this->assertRawCookie($response, 'loa_cert_refresh', '');
    }

    public function test_refresh_auth_service_unavailable_returns_401_and_clears_cookie(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => Http::response('', 503),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'some-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid refresh token']);

        $this->assertRawCookie($response, 'loa_cert_refresh', '');
    }

    public function test_refresh_auth_service_connection_failure_returns_502(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'some-token',
        ]);

        $response->assertStatus(502)
            ->assertJson(['message' => 'Auth service unavailable']);

        $this->assertRawCookie($response, 'loa_cert_refresh', '');
    }

    public function test_refresh_incomplete_response_returns_502_and_clears_cookie(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/refresh' => Http::response([
                'access_token' => 'new-access',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'some-token',
        ]);

        $response->assertStatus(502)
            ->assertJson(['message' => 'Incomplete auth response']);

        $this->assertRawCookie($response, 'loa_cert_refresh', '');
    }

    // ─── POST /api/v1/auth/logout ─────────────────────────────────

    public function test_logout_without_token_returns_204_and_clears_cookie(): void
    {
        $response = $this->postJson('/api/v1/auth/logout', []);

        $response->assertStatus(204);
        $this->assertRawCookie($response, 'loa_cert_refresh', '');
    }

    public function test_logout_with_body_token_forwards_to_auth_platform(): void
    {
        Http::fake();

        $response = $this->postJson('/api/v1/auth/logout', [
            'refresh_token' => 'some-refresh-token',
        ]);

        $response->assertStatus(204);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://auth.example.com/api/v1/auth/logout'
                && json_decode($request->body(), true)['refresh_token'] === 'some-refresh-token';
        });
    }

    public function test_logout_with_cookie_token_forwards_to_auth_platform(): void
    {
        Http::fake();

        $response = $this->call(
            'POST',
            '/api/v1/auth/logout',
            [],
            ['loa_cert_refresh' => 'cookie-refresh-token'],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );

        $response->assertStatus(204);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://auth.example.com/api/v1/auth/logout'
                && json_decode($request->body(), true)['refresh_token'] === 'cookie-refresh-token';
        });
    }

    public function test_logout_auth_service_unreachable_still_clears_cookie(): void
    {
        Http::fake([
            'auth.example.com/api/v1/auth/logout' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $response = $this->postJson('/api/v1/auth/logout', [
            'refresh_token' => 'some-token',
        ]);

        $response->assertStatus(204);
        $this->assertRawCookie($response, 'loa_cert_refresh', '');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function assertRawCookie($response, string $name, ?string $expectedValue = null): void
    {
        $cookies = $response->baseResponse->headers->getCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $name) {
                if ($expectedValue !== null) {
                    $this->assertEquals($expectedValue, $cookie->getValue());
                }
                return;
            }
        }
        $this->fail("Cookie '{$name}' not found in response");
    }

    private function createJwt(array $overrides = []): string
    {
        $secret = config('jwt.secret');

        $claims = array_merge([
            'iss' => 'loa-auth',
            'aud' => 'loa-cert',
            'iat' => time(),
            'exp' => time() + 900,
            'type' => 'access',
            'sub' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'tenant' => [
                'id' => 'tenant-1',
                'slug' => 'loa-e-cert',
            ],
            'permissions' => [],
        ], $overrides);

        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );

        return "$header.$payload.$signature";
    }

    private function encryptPayload(array $data): string
    {
        $keyBytes = hex2bin($this->hexKey);
        $plaintext = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $keyBytes,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return rtrim(base64_encode($nonce . $tag . $ciphertext), '=');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
