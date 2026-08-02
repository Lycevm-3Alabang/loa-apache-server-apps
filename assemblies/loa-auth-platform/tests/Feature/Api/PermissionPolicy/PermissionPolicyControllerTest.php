<?php

namespace Tests\Feature\Api\PermissionPolicy;

use App\Models\Claim;
use App\Models\GroupClaim;
use App\Models\RoutePolicy;
use App\Models\User;
use App\Models\UserClaimOverride;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;
use Tests\Traits\WithJwtClaims;

class PermissionPolicyControllerTest extends TestCase
{
    use RefreshDatabase, WithJwt, WithJwtClaims;

    private function createAdmin(): User
    {
        return $this->createAndLoginAdmin();
    }

    public function testClaimsIndexReturnsEmptyCollection(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/admin/permissions/claims', $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['claims' => []]);
    }

    public function testClaimsIndexReturnsExistingClaims(): void
    {
        $admin = $this->createAdmin();
        $this->createClaim('certificate.read', 'Read certificates');
        $this->createClaim('certificate.write', 'Write certificates');

        $response = $this->getJson('/api/v1/admin/permissions/claims', $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJsonCount(2, 'claims')
            ->assertJsonFragment(['key' => 'certificate.read']);
    }

    public function testClaimsStoreCreatesNewClaim(): void
    {
        $admin = $this->createAdmin();

        $response = $this->postJson('/api/v1/admin/permissions/claims', [
            'key' => 'certificate.read',
            'description' => 'Read certificates',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(201)
            ->assertJson(['claim' => ['key' => 'certificate.read']]);

        $this->assertDatabaseHas('claims', ['key' => 'certificate.read']);
    }

    public function testClaimsStoreValidationFails(): void
    {
        $admin = $this->createAdmin();

        $response = $this->postJson('/api/v1/admin/permissions/claims', [
            'key' => '',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(422);
    }

    public function testClaimsUpdateUpdatesDescription(): void
    {
        $admin = $this->createAdmin();
        $claim = $this->createClaim('certificate.read', 'Old description');

        $response = $this->putJson("/api/v1/admin/permissions/claims/{$claim->id}", [
            'description' => 'Updated description',
        ], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['claim' => ['description' => 'Updated description']]);
    }

    public function testClaimsDestroyDeletesClaim(): void
    {
        $admin = $this->createAdmin();
        $claim = $this->createClaim('certificate.read', 'Read certificates');

        $response = $this->deleteJson("/api/v1/admin/permissions/claims/{$claim->id}", [], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['message' => 'Claim deleted']);

        $this->assertDatabaseMissing('claims', ['id' => $claim->id]);
    }

    public function testRoutePoliciesIndexReturnsEmpty(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/admin/permissions/policies', $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['policies' => []]);
    }

    public function testRoutePoliciesStoreCreatesNewPolicy(): void
    {
        $admin = $this->createAdmin();

        $response = $this->postJson('/api/v1/admin/permissions/policies', [
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(201)
            ->assertJson(['policy' => [
                'app' => 'certificate',
                'method' => 'GET',
                'path' => 'api/v1/certificates',
                'claim_key' => 'certificate.read',
                'filter' => 'all',
            ]]);
    }

    public function testRoutePoliciesStoreValidationFails(): void
    {
        $admin = $this->createAdmin();

        $response = $this->postJson('/api/v1/admin/permissions/policies', [
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'invalid',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(422);
    }

    public function testRoutePoliciesDestroyDeletesPolicy(): void
    {
        $admin = $this->createAdmin();
        $policy = $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');

        $response = $this->deleteJson("/api/v1/admin/permissions/policies/{$policy->id}", [], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['message' => 'Policy deleted']);

        $this->assertDatabaseMissing('route_policies', ['id' => $policy->id]);
    }

    public function testGroupClaimsIndexReturnsEmpty(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/admin/permissions/group-claims', $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['group_claims' => []]);
    }

    public function testGroupClaimsStoreCreatesNewGroupClaim(): void
    {
        $admin = $this->createAdmin();
        $group = UserGroup::factory()->create();
        $claim = $this->createClaim('certificate.read');

        $response = $this->postJson('/api/v1/admin/permissions/group-claims', [
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'scope',
            'scope_id' => 'certificate:own',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(201)
            ->assertJson(['group_claim' => [
                'group_id' => $group->id,
                'claim_key' => 'certificate.read',
                'scope_type' => 'scope',
                'scope_id' => 'certificate:own',
            ]]);
    }

    public function testGroupClaimsStoreValidationFails(): void
    {
        $admin = $this->createAdmin();

        $response = $this->postJson('/api/v1/admin/permissions/group-claims', [
            'group_id' => 'invalid-uuid',
            'claim_key' => 'certificate.read',
            'scope_type' => 'invalid',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(422);
    }

    public function testGroupClaimsDestroyRemovesGroupClaim(): void
    {
        $admin = $this->createAdmin();
        $group = UserGroup::factory()->create();
        $claim = $this->createClaim('certificate.read');
        $groupClaim = GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);

        $response = $this->deleteJson("/api/v1/admin/permissions/group-claims/{$groupClaim->id}", [], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['message' => 'Group claim removed']);

        $this->assertDatabaseMissing('group_claims', ['id' => $groupClaim->id]);
    }

    public function testUserOverridesIndexReturnsEmpty(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/admin/permissions/user-overrides', $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['overrides' => []]);
    }

    public function testUserOverridesStoreCreatesNewOverride(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $this->createClaim('certificate.read');

        $response = $this->postJson('/api/v1/admin/permissions/user-overrides', [
            'user_id' => $user->id,
            'claim_key' => 'certificate.read',
            'granted' => true,
        ], $this->jwtHeaders($admin));

        $response->assertStatus(201)
            ->assertJson(['override' => [
                'user_id' => $user->id,
                'claim_key' => 'certificate.read',
                'granted' => true,
            ]]);
    }

    public function testUserOverridesDestroyRemovesOverride(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $claim = $this->createClaim('certificate.read');
        $override = UserClaimOverride::create([
            'user_id' => $user->id,
            'claim_key' => 'certificate.read',
            'granted' => true,
        ]);

        $response = $this->deleteJson("/api/v1/admin/permissions/user-overrides/{$override->id}", [], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['message' => 'Override removed']);

        $this->assertDatabaseMissing('user_claim_overrides', ['id' => $override->id]);
    }

    public function testAuthorizeReturnsAllowedWhenUserHasClaim(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $user->userGroups()->syncWithoutDetaching([$group->id]);
        $this->createClaim('certificate.read');
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');
        $this->createGroupClaim($group, 'certificate.read');

        $response = $this->postJson('/api/v1/admin/permissions/authorize', [
            'user_id' => $user->id,
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
        ], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['allowed' => true]);
    }

    public function testAuthorizeReturnsDeniedWhenUserMissingClaim(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $this->createClaim('certificate.read');
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read');

        $response = $this->postJson('/api/v1/admin/permissions/authorize', [
            'user_id' => $user->id,
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
        ], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['allowed' => false, 'reason' => 'missing_claim']);
    }

    public function testAuthorizeReturnsForbiddenWhenFilterDenies(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $user->userGroups()->syncWithoutDetaching([$group->id]);
        $this->createClaim('certificate.read');
        $this->createRoutePolicy('certificate', 'GET', 'api/v1/certificates', 'certificate.read', 'scope');
        $this->createGroupClaim($group, 'certificate.read');

        $response = $this->postJson('/api/v1/admin/permissions/authorize', [
            'user_id' => $user->id,
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
        ], $this->jwtHeaders($admin));

        $response->assertOk()
            ->assertJson(['allowed' => false, 'reason' => 'filter_denied']);
    }

    public function testAuthorizeRequiresValidUserId(): void
    {
        $admin = $this->createAdmin();

        $response = $this->postJson('/api/v1/admin/permissions/authorize', [
            'user_id' => '00000000-0000-0000-0000-000000000000',
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
        ], $this->jwtHeaders($admin));

        $response->assertStatus(422);
    }
}