<?php

namespace Tests\Feature\Api\PermissionPolicy;

use App\Models\Claim;
use App\Models\RoutePolicy;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\GroupClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwtClaims;

class ClaimPolicyMiddlewareTest extends TestCase
{
    use RefreshDatabase, WithJwtClaims;

    public function testRouteWithoutPolicyPasses(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/auth/me', $this->jwtHeadersWithClaims($user));

        $response->assertOk();
    }

    public function testRouteWithPolicyAndMatchingClaimPasses(): void
    {
        $claim = $this->createClaim('certificate.read', 'Read certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');

        $response = $this->getJson('/api/v1/certificates', $this->jwtHeadersWithClaims($user, ['certificate.read']));

        $response->assertOk();
    }

    public function testRouteWithPolicyAndMissingClaimReturnsForbidden(): void
    {
        $this->createClaim('certificate.read', 'Read certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');

        $response = $this->getJson('/api/v1/certificates', $this->jwtHeadersWithClaims($user, []));

        $response->assertStatus(403)
            ->assertJson(['reason' => 'missing_claim']);
    }

    public function testRouteWithMultiplePoliciesAllMustPass(): void
    {
        $this->createClaim('certificate.read', 'Read certificates');
        $this->createClaim('certificate.write', 'Write certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.write');

        $response = $this->getJson('/api/v1/certificates', $this->jwtHeadersWithClaims($user, ['certificate.read']));

        $response->assertStatus(403)
            ->assertJson(['reason' => 'missing_claim']);
    }

    public function testRouteWithAllFilterPasses(): void
    {
        $this->createClaim('certificate.read', 'Read certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read', 'all');

        $response = $this->getJson('/api/v1/certificates', $this->jwtHeadersWithClaims($user, ['certificate.read']));

        $response->assertOk();
    }

    public function testRouteWithScopeFilterPassesWhenUserHasScopes(): void
    {
        $this->createClaim('certificate.read', 'Read certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read', 'scope');

        $response = $this->getJson('/api/v1/certificates', $this->jwtHeadersWithClaims($user, ['certificate.read'], ['certificate:own']));

        $response->assertOk();
    }

    public function testRouteWithScopeFilterFailsWhenUserHasNoScopes(): void
    {
        $this->createClaim('certificate.read', 'Read certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read', 'scope');

        $response = $this->getJson('/api/v1/certificates', $this->jwtHeadersWithClaims($user, ['certificate.read'], []));

        $response->assertStatus(403)
            ->assertJson(['reason' => 'filter_denied']);
    }

    public function testUnauthenticatedRequestReturnsUnauthorized(): void
    {
        $this->createClaim('certificate.read', 'Read certificates');
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');

        $response = $this->getJson('/api/v1/certificates');

        $response->assertStatus(401);
    }

    public function testPostMethodWithPolicyPasses(): void
    {
        $this->createClaim('certificate.write', 'Write certificates');
        $user = User::factory()->create();
        $this->createRoutePolicy('certificate', 'POST', 'api/v1/certificates', 'certificate.write');

        $response = $this->postJson('/api/v1/certificates', [], $this->jwtHeadersWithClaims($user, ['certificate.write']));

        $response->assertOk();
    }
}