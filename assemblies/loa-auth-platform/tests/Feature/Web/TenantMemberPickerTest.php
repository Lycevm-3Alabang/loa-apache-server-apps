<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMemberPickerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'status' => 'active',
        ]);

        $this->adminGroup = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators', 'priority' => 1]
        );
        $this->admin->userGroups()->attach($this->adminGroup);

        $permission = Permission::firstOrCreate(
            ['key' => 'users.manage'],
            ['description' => 'Manage users']
        );
        $this->adminGroup->permissions()->syncWithoutDetaching([
            $permission->id => ['granted' => true]
        ]);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
    }

    private function createGroup(string $name, ?Tenant $tenant = null): UserGroup
    {
        return UserGroup::create([
            'name' => $name,
            'description' => "Test group: {$name}",
            'priority' => 10,
            'tenant_id' => $tenant?->id,
        ]);
    }

    // ===== Search endpoint =====

    public function testSearchRequiresAuthentication(): void
    {
        $response = $this->get("/admin/tenants/{$this->tenant->id}/members/search?q=jo");

        $response->assertRedirect();
    }

    public function testSearchReturnsEmptyUnderTwoChars(): void
    {
        User::factory()->create(['email' => 'jo@test.com', 'name' => 'Jo', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=j");

        $response->assertOk();
        $response->assertExactJson(['data' => []]);
    }

    public function testSearchMatchesPartialNameAndEmail(): void
    {
        $a = User::factory()->create(['email' => 'nino.alamo@test.com', 'name' => 'Nino Alamo', 'status' => 'active']);
        User::factory()->create(['email' => 'other@test.com', 'name' => 'Someone Else', 'status' => 'active']);

        $byName = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=nino");
        $byName->assertOk();
        $ids = collect($byName->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($a->id));
        $this->assertCount(1, $ids);

        $byEmail = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=nino.alamo");
        $byEmail->assertOk();
        $this->assertCount(1, $byEmail->json('data'));
    }

    public function testSearchExcludesExistingMembersAndDisabled(): void
    {
        $member = User::factory()->create(['email' => 'member@test.com', 'name' => 'Member One', 'status' => 'active']);
        $member->tenants()->attach($this->tenant->id);
        User::factory()->create(['email' => 'disabled@test.com', 'name' => 'Disabled Guy', 'status' => 'disabled']);
        $visible = User::factory()->create(['email' => 'visible@test.com', 'name' => 'Visible User', 'status' => 'pending']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=test.com");

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');

        $this->assertFalse($emails->contains('member@test.com'));
        $this->assertFalse($emails->contains('disabled@test.com'));
        $this->assertTrue($emails->contains('visible@test.com'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function testSearchTreatsWildcardsLiterally(): void
    {
        User::factory()->create(['email' => 'ab@test.com', 'name' => 'A%B', 'status' => 'active']);
        User::factory()->create(['email' => 'azb@test.com', 'name' => 'AZB', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=a%25b");

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');

        $this->assertTrue($emails->contains('ab@test.com'));
        $this->assertFalse($emails->contains('azb@test.com'));
    }

    public function testSearchRanksExactEmailFirst(): void
    {
        User::factory()->create(['email' => 'zara.jo@test.com', 'name' => 'Zara Jo', 'status' => 'active']);
        $exact = User::factory()->create(['email' => 'jo@test.com', 'name' => 'Jo Exact', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=jo@test.com");

        $response->assertOk();
        $this->assertSame($exact->id, $response->json('data.0.id'));
    }

    public function testSearchResponseShapeIsLimitedToFourKeys(): void
    {
        User::factory()->create(['email' => 'shape@test.com', 'name' => 'Shape', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=shape");

        $row = $response->json('data.0');
        $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'status'], array_keys($row));
    }

    public function testSearchLimitIsTwenty(): void
    {
        for ($i = 0; $i < 25; $i++) {
            User::factory()->create(['email' => "bulk{$i}@test.com", 'name' => "Bulk {$i}", 'status' => 'active']);
        }

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/admin/tenants/{$this->tenant->id}/members/search?q=bulk");

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
    }

    // ===== Tenant show page =====

    public function testShowPageRendersToolbarWithoutDropdown(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);
        $member = User::factory()->create(['status' => 'active']);
        $member->tenants()->attach($this->tenant->id);
        $member->userGroups()->attach($group->id);

        $response = $this->actingAs($this->admin, 'web')->get("/admin/tenants/{$this->tenant->id}");

        $response->assertOk();
        $response->assertSee('Search by name or email…');
        $response->assertSee('Import CSV');
        $response->assertDontSee('<option value="">Select a user…</option>', false);
        $response->assertDontSee('$nonMembers');

        $dom = $response->getContent();
        $this->assertStringContainsString('Unenroll this user from cert-admin', $dom);
        $this->assertStringContainsString('/members/' . $member->id, $dom);
    }

    public function testChipsRenderOnlyForThisTenantsGroups(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $foreignGroup = $this->createGroup('foreign-group', $otherTenant);

        $member = User::factory()->create(['status' => 'active']);
        $member->tenants()->attach($this->tenant->id);
        $member->userGroups()->attach([$foreignGroup->id, $this->adminGroup->id]);

        $response = $this->actingAs($this->admin, 'web')->get("/admin/tenants/{$this->tenant->id}");

        $response->assertOk();
        $this->assertStringNotContainsString('Unenroll this user from foreign-group', $response->getContent());
        $this->assertStringNotContainsString('Unenroll this user from loa-auth-admin', $response->getContent());
    }

    // ===== Removal hygiene =====

    public function testRemoveFromTenantDetachesOnlyTenantScopedGroups(): void
    {
        $tenantGroupA = $this->createGroup('cert-admin', $this->tenant);
        $tenantGroupB = $this->createGroup('cert-staff', $this->tenant);
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $foreignGroup = $this->createGroup('foreign-group', $otherTenant);

        $member = User::factory()->create(['status' => 'active']);
        $member->tenants()->attach($this->tenant->id);
        $member->userGroups()->attach([
            $tenantGroupA->id, $tenantGroupB->id, $foreignGroup->id, $this->adminGroup->id,
        ]);

        $response = $this->actingAs($this->admin, 'web')->post("/admin/tenants/{$this->tenant->id}/members", [
            'action' => 'remove',
            'user_id' => $member->id,
        ]);

        $response->assertRedirect();
        $member->refresh();

        $this->assertFalse($member->tenants()->whereKey($this->tenant->id)->exists());
        $this->assertFalse($member->userGroups()->whereKey($tenantGroupA->id)->exists());
        $this->assertFalse($member->userGroups()->whereKey($tenantGroupB->id)->exists());

        $this->assertTrue($member->userGroups()->whereKey($foreignGroup->id)->exists());
        $this->assertTrue($member->userGroups()->whereKey($this->adminGroup->id)->exists());
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }
}
