<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGroupsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->create();
        $group = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators']
        );
        $this->admin->userGroups()->attach($group->id);
    }

    public function testGroupsIndex(): void
    {
        UserGroup::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'web')
            ->get('/admin/groups');

        $response->assertOk();
    }

    public function testGroupsShow(): void
    {
        $group = UserGroup::factory()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/groups/{$group->id}");

        $response->assertOk();
    }

    public function testGroupsStore(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post('/admin/groups', [
                'name' => 'Faculty',
                'description' => 'Teaching staff',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_groups', [
            'name' => 'Faculty',
            'description' => 'Teaching staff',
        ]);
    }

    public function testGroupsStoreDuplicateName(): void
    {
        UserGroup::factory()->create(['name' => 'Faculty', 'tenant_id' => null]);

        $response = $this->actingAs($this->admin, 'web')
            ->post('/admin/groups', [
                'name' => 'Faculty',
            ]);

        $response->assertSessionHas('error');
    }

    public function testGroupsPermissions(): void
    {
        $group = UserGroup::factory()->create();
        $perm = Permission::factory()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/groups/{$group->id}/permissions", [
                'permissions' => [$perm->id],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_group_permission', [
            'user_group_id' => $group->id,
            'permission_id' => $perm->id,
            'granted' => true,
        ]);
    }

    public function testGroupsMembersAdd(): void
    {
        $group = UserGroup::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/groups/{$group->id}/members", [
                'user_id' => $user->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $group->id,
        ]);
    }

    public function testGroupsMembersRemove(): void
    {
        $group = UserGroup::factory()->create();
        $user = User::factory()->create();

        $group->users()->attach($user->id);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/groups/{$group->id}/members/{$user->id}/remove");

        $response->assertRedirect();

        $this->assertDatabaseMissing('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $group->id,
        ]);
    }
}
