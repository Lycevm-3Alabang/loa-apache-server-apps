<?php

namespace Tests\Unit;

use App\Services\JWTService;
use PHPUnit\Framework\TestCase;

class JWTServiceTest extends TestCase
{
    private JWTService $jwt;
    private string $secret = 'test-secret-key-for-unit-tests';

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JWTService($this->secret);
    }

    public function test_validate_returns_claims_for_valid_access_token(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'type' => 'access',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa'],
            'groups' => ['cert-admin'],
            'permissions' => ['read:/api/v1/events'],
        ]);

        $claims = $this->jwt->validate($token);

        $this->assertNotNull($claims);
        $this->assertEquals('user-123', $claims['sub']);
        $this->assertEquals('test@example.com', $claims['email']);
        $this->assertEquals('access', $claims['type']);
    }

    public function test_validate_rejects_expired_token(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'type' => 'access',
            'exp' => time() - 3600,
        ]);

        $this->assertNull($this->jwt->validate($token));
    }

    public function test_validate_rejects_refresh_token(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'type' => 'refresh',
            'exp' => time() + 3600,
        ]);

        $this->assertNull($this->jwt->validate($token));
    }

    public function test_validate_rejects_token_with_wrong_signature(): void
    {
        $token = $this->createTokenWithSecret('wrong-secret', [
            'sub' => 'user-123',
            'type' => 'access',
            'exp' => time() + 3600,
        ]);

        $this->assertNull($this->jwt->validate($token));
    }

    public function test_validate_rejects_malformed_token(): void
    {
        $this->assertNull($this->jwt->validate('not.a.valid.jwt'));
    }

    public function test_validate_rejects_token_with_missing_parts(): void
    {
        $this->assertNull($this->jwt->validate('only.two'));
    }

    public function test_validate_rejects_token_with_no_type(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'exp' => time() + 3600,
        ]);

        $this->assertNull($this->jwt->validate($token));
    }

    public function test_validate_handles_token_expiring_at_current_second(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'type' => 'access',
            'exp' => time(),
        ]);

        $this->assertNotNull($this->jwt->validate($token));
    }

    public function test_validate_preserves_tenant_claim(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'type' => 'access',
            'tenant' => ['id' => 't-1', 'slug' => 'loa'],
        ]);

        $claims = $this->jwt->validate($token);

        $this->assertNotNull($claims);
        $this->assertEquals('loa', $claims['tenant']['slug']);
    }

    public function test_validate_preserves_permissions_claim(): void
    {
        $token = $this->createToken([
            'sub' => 'user-123',
            'type' => 'access',
            'permissions' => ['read:/api/v1/events', 'write:/api/v1/certificates'],
        ]);

        $claims = $this->jwt->validate($token);

        $this->assertNotNull($claims);
        $this->assertCount(2, $claims['permissions']);
    }

    private function createToken(array $payload): string
    {
        return $this->createTokenWithSecret($this->secret, $payload);
    }

    private function createTokenWithSecret(string $secret, array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payloadWithDefaults = array_merge([
            'iat' => time(),
            'exp' => time() + 900,
        ], $payload);

        $payloadEncoded = $this->base64UrlEncode(json_encode($payloadWithDefaults));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", $secret, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
