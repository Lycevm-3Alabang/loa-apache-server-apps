<?php

namespace Tests\Unit;

use App\Http\Middleware\JwtMiddleware;
use App\Services\JWTService;
use Illuminate\Http\Request;
use Tests\TestCase;

class JwtMiddlewareTest extends TestCase
{
    private string $secret = 'test-secret-for-middleware';

    private function createToken(array $overrides = []): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = array_merge([
            'sub' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'type' => 'access',
            'tenant' => ['id' => 'tenant-1', 'slug' => 'loa'],
            'groups' => ['student'],
            'permissions' => ['read:/api/v1/semesters'],
            'iat' => time(),
            'exp' => time() + 900,
        ], $overrides);
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payloadEncoded", $this->secret, true)), '+/', '-_'), '=');
        return "$header.$payloadEncoded.$signature";
    }

    private function middleware(): JwtMiddleware
    {
        config(['consult-platform.tenant_slug' => 'loa']);
        return new JwtMiddleware(new JWTService($this->secret));
    }

    public function test_valid_token_passes(): void
    {
        $token = $this->createToken();
        $request = Request::create('/api/v1/semesters', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $claims = null;
        $consultUser = null;
        $response = $this->middleware()->handle($request, function ($req) use (&$claims, &$consultUser) {
            $claims = $req->attributes->get('jwt_claims');
            $consultUser = $req->attributes->get('consult_user');
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($claims);
        $this->assertEquals('user-123', $claims['sub']);
        $this->assertNotNull($consultUser);
        $this->assertEquals('test@example.com', $consultUser['email']);
        $this->assertEquals('loa', $consultUser['tenant']['slug']);
    }

    public function test_rejects_missing_token_with_401(): void
    {
        $request = Request::create('/api/v1/semesters', 'GET');

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Missing bearer token', $response->getData(true)['message']);
    }

    public function test_rejects_invalid_signature_with_401(): void
    {
        $token = $this->createToken();
        $request = Request::create('/api/v1/semesters', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $wrongMiddleware = new JwtMiddleware(new JWTService('wrong-secret'));
        $response = $wrongMiddleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid or expired token', $response->getData(true)['message']);
    }

    public function test_rejects_expired_token_with_401(): void
    {
        $token = $this->createToken(['exp' => time() - 3600]);
        $request = Request::create('/api/v1/semesters', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid or expired token', $response->getData(true)['message']);
    }

    public function test_rejects_wrong_type_token_with_401(): void
    {
        $token = $this->createToken(['type' => 'refresh']);
        $request = Request::create('/api/v1/semesters', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid or expired token', $response->getData(true)['message']);
    }

    public function test_rejects_tenant_mismatch_with_403(): void
    {
        $token = $this->createToken(['tenant' => ['id' => 't-2', 'slug' => 'wrong']]);
        $request = Request::create('/api/v1/semesters', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertEquals('Forbidden', $body['message']);
        $this->assertEquals('tenant_mismatch', $body['reason']);
    }
}
