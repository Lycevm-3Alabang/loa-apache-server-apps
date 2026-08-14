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
            'groups' => ['cert-admin'],
            'permissions' => ['read:/api/v1/events'],
            'iat' => time(),
            'exp' => time() + 900,
        ], $overrides);
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payloadEncoded", $this->secret, true)), '+/', '-_'), '=');
        return "$header.$payloadEncoded.$signature";
    }

    private function middleware(): JwtMiddleware
    {
        config(['cert-platform.tenant_slug' => 'loa']);
        return new JwtMiddleware(new JWTService($this->secret));
    }

    public function test_sets_attributes_on_valid_token(): void
    {
        $token = $this->createToken();
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $claims = null;
        $this->middleware()->handle($request, function ($req) use (&$claims) {
            $claims = $req->attributes->get('jwt_claims');
            return response()->json(['ok' => true]);
        });

        $this->assertNotNull($claims);
        $this->assertEquals('user-123', $claims['sub']);
        $this->assertEquals('test@example.com', $claims['email']);
    }

    public function test_sets_cert_user_attribute(): void
    {
        $token = $this->createToken();
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $certUser = null;
        $this->middleware()->handle($request, function ($req) use (&$certUser) {
            $certUser = $req->attributes->get('cert_user');
            return response()->json(['ok' => true]);
        });

        $this->assertNotNull($certUser);
        $this->assertEquals('user-123', $certUser['sub']);
        $this->assertEquals('loa', $certUser['tenant']['slug']);
    }

    public function test_rejects_missing_token(): void
    {
        $request = Request::create('/api/v1/events', 'GET');

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_expired_token(): void
    {
        $token = $this->createToken(['exp' => time() - 3600]);
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_wrong_signature(): void
    {
        $token = $this->createToken();
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $wrongMiddleware = new JwtMiddleware(new JWTService('wrong-secret'));
        $response = $wrongMiddleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_tenant_mismatch(): void
    {
        $token = $this->createToken(['tenant' => ['id' => 't-2', 'slug' => 'other']]);
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_rejects_non_access_token(): void
    {
        $token = $this->createToken(['type' => 'refresh']);
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $response = $this->middleware()->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_sets_jwt_token_attribute(): void
    {
        $token = $this->createToken();
        $request = Request::create('/api/v1/events', 'GET');
        $request->headers->set('Authorization', "Bearer $token");

        $storedToken = null;
        $this->middleware()->handle($request, function ($req) use (&$storedToken) {
            $storedToken = $req->attributes->get('jwt_token');
            return response()->json(['ok' => true]);
        });

        $this->assertEquals($token, $storedToken);
    }
}
