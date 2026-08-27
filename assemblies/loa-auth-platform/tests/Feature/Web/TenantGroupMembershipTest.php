<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantGroupMembershipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private UserGroup $tenantGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);

        $adminGroup = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators'],
        );
        $this->admin->userGroups()->attach($adminGroup);

        $this->tenant = Tenant::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'slug' => 'test-tenant',
            'name' => 'Test Tenant',
            'status' => 'active',
        ]);

        $this->tenant->users()->attach($this->admin);

        $this->tenantGroup = UserGroup::create([
            'name' => 'Staff',
            'description' => 'Tenant staff',
            'priority' => 10,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ─── I1 invariant ──────────────────────────────────────────────

    public function testI1AddToGroupThrowsWhenUserLacksTenantPivot(): void
    {
        $user = User::factory()->create();
        $service = app(\App\Services\AuthorizationService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->addToGroup($user->id, $this->tenantGroup->id);
    }

    public function testI1AddToGroupSucceedsWhenUserHasTenantPivot(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user);
        $service = app(\App\Services\AuthorizationService::class);

        $service->addToGroup($user->id, $this->tenantGroup->id);

        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testI1AddToGroupSucceedsForPlatformGroup(): void
    {
        $platformGroup = UserGroup::factory()->create(['tenant_id' => null]);
        $user = User::factory()->create();
        $service = app(\App\Services\AuthorizationService::class);

        $service->addToGroup($user->id, $platformGroup->id);

        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $platformGroup->id,
        ]);
    }

    public function testI1AddToGroupTransactionalCreatesPivotAndGroupMembership(): void
    {
        $user = User::factory()->create();
        $service = app(\App\Services\AuthorizationService::class);

        $service->addToGroupTransactional($user->id, $this->tenantGroup->id);

        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testI1AddToGroupTransactionalSucceedsWhenPivotExists(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user);
        $service = app(\App\Services\AuthorizationService::class);

        $service->addToGroupTransactional($user->id, $this->tenantGroup->id);

        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    // ─── M8: self-revocation guard ─────────────────────────────────

    public function testM8RemoveFromGroupThrowsOnSelfRevocation(): void
    {
        $this->tenantGroup->name = config('auth-web.admin_group');
        $this->tenantGroup->save();
        $this->tenantGroup->users()->attach($this->admin);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$this->admin->id}/remove");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $this->admin->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testM8RemoveFromGroupSucceedsForOtherUser(): void
    {
        $this->tenantGroup->name = config('auth-web.admin_group');
        $this->tenantGroup->save();
        $other = User::factory()->create();
        $this->tenantGroup->users()->attach($other);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$other->id}/remove");

        $response->assertRedirect();
        $this->assertDatabaseMissing('user_user_group', [
            'user_id' => $other->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testM8RemoveFromGroupSucceedsForSelfOnNonAdminGroup(): void
    {
        $this->tenantGroup->users()->attach($this->admin);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$this->admin->id}/remove");

        $response->assertRedirect();
        $this->assertDatabaseMissing('user_user_group', [
            'user_id' => $this->admin->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    // ─── M6: platform group page scope ─────────────────────────────

    public function testM6TenantGroupReturns404OnPlatformRoute(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/groups/{$this->tenantGroup->id}");

        $response->assertNotFound();
    }

    public function testM6PlatformGroupShowsMemberControls(): void
    {
        $platformGroup = UserGroup::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/groups/{$platformGroup->id}");

        $response->assertOk();
    }

    // ─── tenantGroupMemberSearch ───────────────────────────────────

    public function testSearchReturnsPrimaryTierForTenantMembersNotInGroup(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $this->tenant->users()->attach($member);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/search?q={$member->email}");

        $response->assertOk()
            ->assertJsonPath('tier', 'primary')
            ->assertJsonCount(1, 'data');
    }

    public function testSearchReturnsSecondaryTierForNonTenantUsers(): void
    {
        $outsider = User::factory()->create(['email' => 'outsider@example.com', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/search?q=outsider");

        $response->assertOk()
            ->assertJsonPath('tier', 'secondary')
            ->assertJsonCount(1, 'data');
    }

    public function testSearchReturnsEmptyForShortQuery(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/search?q=a");

        $response->assertOk()
            ->assertJsonPath('tier', 'none')
            ->assertJsonCount(0, 'data');
    }

    // ─── tenantGroupMembersStore ───────────────────────────────────

    public function testStoreAddsPrimaryTierUser(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $this->tenant->users()->attach($member);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members", [
                'user_id' => $member->id,
                'tier' => 'primary',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $member->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testStoreAddsSecondaryTierUserWithPivot(): void
    {
        $outsider = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members", [
                'user_id' => $outsider->id,
                'tier' => 'secondary',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $outsider->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $outsider->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testStoreRejectsDuplicateMembership(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $this->tenant->users()->attach($member);
        $this->tenantGroup->users()->attach($member);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members", [
                'user_id' => $member->id,
                'tier' => 'primary',
            ]);

        $response->assertSessionHas('error');
    }

    // ─── tenantGroupMemberRemoveConfirm ────────────────────────────

    public function testRemoveConfirmShowsPage(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member);
        $this->tenantGroup->users()->attach($member);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$member->id}/remove");

        $response->assertOk();
    }

    public function testRemoveConfirmReturns404ForNonMember(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$user->id}/remove");

        $response->assertNotFound();
    }

    // ─── tenantGroupMemberRemove ───────────────────────────────────

    public function testRemoveRemovesMember(): void
    {
        $member = User::factory()->create();
        $this->tenant->users()->attach($member);
        $this->tenantGroup->users()->attach($member);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$member->id}/remove");

        $response->assertRedirect();
        $this->assertDatabaseMissing('user_user_group', [
            'user_id' => $member->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    public function testRemoveRejectsSelfRevocationFromAdminGroup(): void
    {
        $this->tenantGroup->name = config('auth-web.admin_group');
        $this->tenantGroup->save();
        $this->tenantGroup->users()->attach($this->admin);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/members/{$this->admin->id}/remove");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $this->admin->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);
    }

    // ─── tenantGroupPlatformPermissions ────────────────────────────

    public function testPlatformPermissionsTogglesPermission(): void
    {
        $perm = Permission::factory()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/platform-permissions", [
                'permissions' => [
                    ['id' => $perm->id, 'granted' => true],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_group_permission', [
            'user_group_id' => $this->tenantGroup->id,
            'permission_id' => $perm->id,
            'granted' => true,
        ]);
    }

    public function testPlatformPermissionsRejectsInvalidData(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/groups/{$this->tenantGroup->id}/platform-permissions", [
                'permissions' => 'not-an-array',
            ]);

        $response->assertSessionHasErrors();
    }

    // ─── repair-i1-violations command ──────────────────────────────

    public function testRepairCommandExits0WhenClean(): void
    {
        $exitCode = Artisan::call('auth:repair-i1-violations');

        $this->assertEquals(0, $exitCode);
    }

    public function testRepairCommandExits1WhenViolationsFound(): void
    {
        $user = User::factory()->create();
        DB::table('user_user_group')->insert([
            'user_id' => $user->id,
            'user_group_id' => $this->tenantGroup->id,
        ]);

        $exitCode = Artisan::call('auth:repair-i1-violations');

        $this->assertEquals(1, $exitCode);
    }
}
