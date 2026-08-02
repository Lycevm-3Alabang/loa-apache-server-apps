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

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function testClaimCanHaveRoutePolicies(): void
    {
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ]);

        $this->assertCount(1, $claim->routePolicies);
    }

    public function testClaimCanHaveGroupClaims(): void
    {
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        $group = UserGroup::factory()->create();
        GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);

        $this->assertCount(1, $claim->groupClaims);
    }

    public function testClaimCanHaveUserOverrides(): void
    {
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        $user = User::factory()->create();
        UserClaimOverride::create([
            'user_id' => $user->id,
            'claim_key' => 'certificate.read',
            'granted' => true,
        ]);

        $this->assertCount(1, $claim->userOverrides);
    }

    public function testRoutePolicyBelongsToClaim(): void
    {
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        $policy = RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ]);

        $this->assertNotNull($policy->claim);
        $this->assertEquals('certificate.read', $policy->claim->key);
    }

    public function testGroupClaimBelongsToGroup(): void
    {
        $group = UserGroup::factory()->create();
        $groupClaim = GroupClaim::create([
            'group_id' => $group->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);

        $this->assertNotNull($groupClaim->group);
        $this->assertEquals($group->id, $groupClaim->group->id);
    }

    public function testGroupClaimBelongsToClaim(): void
    {
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        $groupClaim = GroupClaim::create([
            'group_id' => UserGroup::factory()->create()->id,
            'claim_key' => 'certificate.read',
            'scope_type' => 'none',
        ]);

        $this->assertNotNull($groupClaim->claim);
        $this->assertEquals('certificate.read', $groupClaim->claim->key);
    }

    public function testUserClaimOverrideBelongsToUser(): void
    {
        $user = User::factory()->create();
        $override = UserClaimOverride::create([
            'user_id' => $user->id,
            'claim_key' => 'certificate.read',
            'granted' => true,
        ]);

        $this->assertNotNull($override->user);
        $this->assertEquals($user->id, $override->user->id);
    }

    public function testUserClaimOverrideBelongsToClaim(): void
    {
        $claim = Claim::create(['key' => 'certificate.read', 'description' => 'Read certificates']);
        $override = UserClaimOverride::create([
            'user_id' => User::factory()->create()->id,
            'claim_key' => 'certificate.read',
            'granted' => true,
        ]);

        $this->assertNotNull($override->claim);
        $this->assertEquals('certificate.read', $override->claim->key);
    }

    public function testRoutePolicyHasDefaultFilterAll(): void
    {
        $policy = RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
        ]);

        $this->assertEquals('all', $policy->filter);
    }

    public function testGroupClaimHasDefaultScopeTypeNone(): void
    {
        $groupClaim = GroupClaim::create([
            'group_id' => UserGroup::factory()->create()->id,
            'claim_key' => 'certificate.read',
        ]);

        $this->assertEquals('none', $groupClaim->scope_type);
    }

    public function testUserClaimOverrideDefaultsGrantedTrue(): void
    {
        $override = UserClaimOverride::create([
            'user_id' => User::factory()->create()->id,
            'claim_key' => 'certificate.read',
        ]);

        $this->assertTrue($override->granted);
    }
}