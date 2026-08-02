<?php

namespace Tests\Feature\Api\PermissionPolicy;

use App\Models\Claim;
use App\Models\GroupClaim;
use App\Models\User;
use App\Models\UserClaimOverride;
use App\Models\UserGroup;
use App\Services\PermissionPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionPolicyService $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(PermissionPolicyService::class);
    }

    public function testResolveUserClaimsReturnsEmptyForUnknownUser(): void
    {
        $claims = $this->policy->resolveUserClaims('nonexistent-id');

        $this->assertEmpty($claims);
    }

    public function testResolveUserClaimsReturnsGroupClaims(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);

        $user->userGroups()->syncWithoutDetaching([$group->id]);
        GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);

        $claims = $this->policy->resolveUserClaims($user->id);

        $this->assertContains('certificate.read', $claims);
    }

    public function testResolveUserClaimsOverrideWinsOverGroupClaim(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);

        $user->userGroups()->syncWithoutDetaching([$group->id]);
        GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);

        UserClaimOverride::create([
            'user_id' => $user->id,
            'claim_key' => 'certificate.read',
            'granted' => false,
        ]);

        $claims = $this->policy->resolveUserClaims($user->id);

        $this->assertNotContains('certificate.read', $claims);
    }

    public function testResolveUserClaimsUserOverrideAddsClaim(): void
    {
        $user = User::factory()->create();
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);

        UserClaimOverride::create([
            'user_id' => $user->id,
            'claim_key' => 'certificate.read',
            'granted' => true,
        ]);

        $claims = $this->policy->resolveUserClaims($user->id);

        $this->assertContains('certificate.read', $claims);
    }

    public function testResolveUserClaimsMultipleGroupClaims(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $user->userGroups()->syncWithoutDetaching([$group->id]);
        GroupClaim::create(['group_id' => $group->id, 'claim_key' => 'certificate.read', 'scope_type' => 'none']);
        GroupClaim::create(['group_id' => $group->id, 'claim_key' => 'certificate.write', 'scope_type' => 'none']);

        $claims = $this->policy->resolveUserClaims($user->id);

        $this->assertContains('certificate.read', $claims);
        $this->assertContains('certificate.write', $claims);
    }

    public function testResolveUserScopesReturnsEmptyForUserWithNoScopedClaims(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $user->userGroups()->syncWithoutDetaching([$group->id]);
        GroupClaim::create(['group_id' => $group->id, 'claim_key' => 'certificate.read', 'scope_type' => 'none']);

        $scopes = $this->policy->resolveUserScopes($user->id);

        $this->assertEmpty($scopes);
    }

    public function testResolveUserScopesReturnsScopesFromGroupClaims(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $user->userGroups()->syncWithoutDetaching([$group->id]);
        GroupClaim::create(['group_id' => $group->id, 'claim_key' => 'certificate.read', 'scope_type' => 'scope', 'scope_id' => 'certificate:own']);

        $scopes = $this->policy->resolveUserScopes($user->id);

        $this->assertContains('scope:certificate:own', $scopes);
    }

    public function testResolveUserScopesReturnsEmptyForUnknownUser(): void
    {
        $scopes = $this->policy->resolveUserScopes('nonexistent-id');

        $this->assertEmpty($scopes);
    }

    public function testAuthorizeReturnsAllowedWhenNoPoliciesExist(): void
    {
        $user = User::factory()->create();

        $result = $this->policy->authorize($user->id, null, 'certificate', 'GET', 'api/v1/certificates');

        $this->assertTrue($result['allowed']);
    }

    public function testAuthorizeReturnsDeniedWhenClaimMissing(): void
    {
        $user = User::factory()->create();
        Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        \App\Models\RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ]);

        $result = $this->policy->authorize($user->id, null, 'certificate', 'GET', 'api/v1/certificates');

        $this->assertFalse($result['allowed']);
        $this->assertEquals('missing_claim', $result['reason']);
    }

    public function testAuthorizeReturnsAllowedWhenClaimPresent(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $user->userGroups()->syncWithoutDetaching([$group->id]);

        Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);
        \App\Models\RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ]);

        $result = $this->policy->authorize($user->id, null, 'certificate', 'GET', 'api/v1/certificates');

        $this->assertTrue($result['allowed']);
    }

    public function testAuthorizeReturnsDeniedWhenFilterDenies(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $user->userGroups()->syncWithoutDetaching([$group->id]);

        Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);
        \App\Models\RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'scope',
        ]);

        $result = $this->policy->authorize($user->id, null, 'certificate', 'GET', 'api/v1/certificates');

        $this->assertFalse($result['allowed']);
        $this->assertEquals('filter_denied', $result['reason']);
    }

    public function testAuthorizeReturnsAllowedWhenScopeFilterAndUserHasScopes(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $user->userGroups()->syncWithoutDetaching([$group->id]);

        Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'scope',
            'scope_id' => 'certificate:own',
        ]);
        \App\Models\RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'scope',
        ]);

        $result = $this->policy->authorize($user->id, null, 'certificate', 'GET', 'api/v1/certificates');

        $this->assertTrue($result['allowed']);
    }
}