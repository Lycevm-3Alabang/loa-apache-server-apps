<?php

namespace Tests\Unit;

use App\Http\Middleware\EndpointPolicyMiddleware;
use Illuminate\Http\Request;
use Tests\TestCase;

class EndpointPolicyMiddlewareTest extends TestCase
{
    private EndpointPolicyMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EndpointPolicyMiddleware();
    }

    private function setCatalog(array $catalog): void
    {
        config(['cert-endpoints.catalog' => $catalog]);
        config(['cert-endpoints.public' => [
            '/api/v1/verify/{certificate_number}',
            '/api/v1/view/{id}',
        ]]);
    }

    private function requestWithClaims(string $method, string $path, array $permissions): Request
    {
        $request = Request::create($path, $method);
        $request->attributes->set('jwt_claims', [
            'sub' => 'user-1',
            'permissions' => $permissions,
        ]);
        return $request;
    }

    public function test_allows_sufficient_level(): void
    {
        $this->setCatalog([
            ['method' => 'POST', 'path' => '/api/v1/events', 'required_level' => 'write'],
        ]);

        $request = $this->requestWithClaims('POST', '/api/v1/events', ['write:/api/v1/events']);
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
        $this->assertEquals('write', $request->attributes->get('jwt_endpoint_level'));
    }

    public function test_rejects_insufficient_level(): void
    {
        $this->setCatalog([
            ['method' => 'POST', 'path' => '/api/v1/events', 'required_level' => 'write'],
        ]);

        $request = $this->requestWithClaims('POST', '/api/v1/events', ['read:/api/v1/events']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('insufficient_level', $body['reason']);
    }

    public function test_rejects_no_matching_permission(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/events', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/events', []);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('no_access', $body['reason']);
    }

    public function test_rejects_deny_level(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/events', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/events', ['deny:/api/v1/events']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('denied', $body['reason']);
    }

    public function test_rejects_unknown_path_not_in_catalog(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/events', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/unknown', ['read:/api/v1/unknown']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('no_catalog_entry', $body['reason']);
    }

    public function test_allows_public_path(): void
    {
        $this->setCatalog([]);

        $request = Request::create('/api/v1/verify/CERT-0001', 'GET');
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
    }

    public function test_path_params_are_matched(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/events/{id}', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/events/abc-123', ['read:/api/v1/events/{id}']);
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
    }

    public function test_admin_level_covers_write_requirement(): void
    {
        $this->setCatalog([
            ['method' => 'POST', 'path' => '/api/v1/certificates/{id}/revoke', 'required_level' => 'admin'],
        ]);

        $request = $this->requestWithClaims('POST', '/api/v1/certificates/abc/revoke', ['admin:/api/v1/certificates/{id}/revoke']);
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
    }

    public function test_write_covers_admin_because_write_ordinal_is_higher(): void
    {
        $this->setCatalog([
            ['method' => 'POST', 'path' => '/api/v1/certificates/{id}/revoke', 'required_level' => 'admin'],
        ]);

        $request = $this->requestWithClaims('POST', '/api/v1/certificates/abc/revoke', ['write:/api/v1/certificates/{id}/revoke']);
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
    }

    public function test_stores_granted_level_on_success(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/events', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/events', ['read:/api/v1/events']);
        $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals('read', $request->attributes->get('jwt_endpoint_level'));
    }

    public function test_rejects_when_no_claims(): void
    {
        $request = Request::create('/api/v1/events', 'GET');
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_wildcard_method_permission_matches(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/events/{id}/attendees', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/events/abc/attendees', ['read:/api/v1/events/{id}/attendees']);
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
    }
}
