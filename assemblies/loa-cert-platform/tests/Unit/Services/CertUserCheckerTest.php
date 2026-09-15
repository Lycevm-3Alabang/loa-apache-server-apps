<?php

namespace Tests\Unit\Services;

use App\Services\CertUserChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CertUserCheckerTest extends TestCase
{
    private string $authBaseUrl = 'https://auth.lyceumalabang.edu.ph';
    private string $apiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth-platform.base_url' => $this->authBaseUrl,
            'auth-platform.api_key' => $this->apiKey,
            'auth-platform.http_timeout' => 5,
        ]);
    }

    public function test_resolve_activation_returns_token_url_when_invited(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members/invite" => Http::response([
                'status' => 'invited',
                'token' => str_repeat('a', 64),
            ], 201),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->resolveActivation('New User', 'new@example.com');

        $this->assertFalse($result['isRegistered']);
        $this->assertEquals(
            "{$this->authBaseUrl}/set-password?token=" . str_repeat('a', 64),
            $result['activateUrl']
        );

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request['email'] === 'new@example.com'
                && $request['name'] === 'New User'
                && $request['groups'] === ['cert-user']
                && $request['notify'] === false;
        });
    }

    public function test_resolve_activation_returns_registered_when_already_registered(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members/invite" => Http::response([
                'status' => 'already_registered',
            ], 200),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->resolveActivation('Old User', 'old@example.com');

        $this->assertTrue($result['isRegistered']);
        $this->assertNull($result['activateUrl']);
    }

    public function test_resolve_activation_returns_registered_when_api_key_missing(): void
    {
        config(['auth-platform.api_key' => '']);

        $checker = new CertUserChecker();
        $result = $checker->resolveActivation('New User', 'new@example.com');

        $this->assertTrue($result['isRegistered']);
        $this->assertNull($result['activateUrl']);
    }

    public function test_resolve_activation_returns_registered_when_api_fails(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members/invite" => Http::response([], 500),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->resolveActivation('New User', 'new@example.com');

        $this->assertTrue($result['isRegistered']);
        $this->assertNull($result['activateUrl']);
    }

    public function test_resolve_activation_returns_registered_when_token_missing(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members/invite" => Http::response([
                'status' => 'invited',
            ], 201),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->resolveActivation('New User', 'new@example.com');

        $this->assertTrue($result['isRegistered']);
        $this->assertNull($result['activateUrl']);
    }

    public function test_resolve_activation_returns_registered_when_api_timeout(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members/invite" => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $checker = new CertUserChecker();
        $result = $checker->resolveActivation('New User', 'new@example.com');

        $this->assertTrue($result['isRegistered']);
        $this->assertNull($result['activateUrl']);
    }
}
