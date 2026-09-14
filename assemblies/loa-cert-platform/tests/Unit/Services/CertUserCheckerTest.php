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

    public function test_is_registered_returns_true_when_member_exists(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members*" => Http::response([
                'data' => [
                    ['id' => '1', 'email' => 'user@example.com'],
                ],
            ], 200),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->isRegistered('user@example.com');

        $this->assertTrue($result);
    }

    public function test_is_registered_returns_false_when_email_not_found(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members*" => Http::response([
                'data' => [],
            ], 200),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->isRegistered('unknown@example.com');

        $this->assertFalse($result);
    }

    public function test_is_registered_returns_true_when_api_key_missing(): void
    {
        config(['auth-platform.api_key' => '']);

        $checker = new CertUserChecker();
        $result = $checker->isRegistered('user@example.com');

        $this->assertTrue($result);
    }

    public function test_is_registered_returns_true_when_api_fails(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members*" => Http::response([], 500),
        ]);

        $checker = new CertUserChecker();
        $result = $checker->isRegistered('user@example.com');

        $this->assertTrue($result);
    }

    public function test_is_registered_returns_true_when_api_timeout(): void
    {
        Http::fake([
            "{$this->authBaseUrl}/api/v1/tenant/members*" => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $checker = new CertUserChecker();
        $result = $checker->isRegistered('user@example.com');

        $this->assertTrue($result);
    }

    public function test_get_activate_url_builds_correct_url(): void
    {
        config(['auth-platform.base_url' => $this->authBaseUrl]);

        $checker = new CertUserChecker();
        $url = $checker->getActivateUrl('CERT-001', 'user@example.com');

        $expected = "{$this->authBaseUrl}/set-password/cert?" . http_build_query([
            'cert' => 'CERT-001',
            'email' => 'user@example.com',
        ]);

        $this->assertEquals($expected, $url);
    }
}
