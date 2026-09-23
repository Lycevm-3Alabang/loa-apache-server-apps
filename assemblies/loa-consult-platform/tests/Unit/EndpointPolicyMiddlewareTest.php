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

    private function setCatalog(array $catalog, array $public = []): void
    {
        config(['consult-endpoints.catalog' => $catalog]);
        config(['consult-endpoints.public' => $public]);
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

    public function test_public_path_passes_tokenless(): void
    {
        $this->setCatalog(
            [['method' => 'GET', 'path' => '/api/v1/semesters', 'required_level' => 'read']],
            ['/api/v1/health', '/api/v1/semesters/count-active']
        );

        $request = Request::create('/api/v1/health', 'GET');
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
    }

    public function test_unknown_path_rejected_closed_by_default(): void
    {
        $this->setCatalog(
            [['method' => 'GET', 'path' => '/api/v1/semesters', 'required_level' => 'read']],
            ['/api/v1/health']
        );

        $request = $this->requestWithClaims('GET', '/api/v1/unknown', ['read:/api/v1/unknown']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('no_catalog_entry', $body['reason']);
    }

    public function test_insufficient_level_rejected(): void
    {
        $this->setCatalog([
            ['method' => 'POST', 'path' => '/api/v1/appointments', 'required_level' => 'write'],
        ]);

        $request = $this->requestWithClaims('POST', '/api/v1/appointments', ['read:/api/v1/appointments']);
        $response = $this->middleware->handle($request, fn ($req) => response()->json(['ok' => true]));

        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('insufficient_level', $body['reason']);
    }

    public function test_sufficient_level_passes_and_stores_granted_level(): void
    {
        $this->setCatalog([
            ['method' => 'GET', 'path' => '/api/v1/appointments/{id}', 'required_level' => 'read'],
        ]);

        $request = $this->requestWithClaims('GET', '/api/v1/appointments/abc-123', ['read:/api/v1/appointments/{id}']);
        $called = false;
        $this->middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
        $this->assertEquals('read', $request->attributes->get('jwt_endpoint_level'));
    }

    public function test_real_catalog_has_113_gated_plus_5_public(): void
    {
        $catalog = config('consult-endpoints.catalog', []);
        $public = config('consult-endpoints.public', []);

        $this->assertCount(113, $catalog);
        $this->assertCount(5, $public);
    }
}
