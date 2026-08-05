<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
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

        $perm = \App\Models\Permission::firstOrCreate(
            ['key' => 'users.manage'],
            ['description' => 'Manage users']
        );
        $group->permissions()->syncWithoutDetaching([
            $perm->id => ['granted' => true, 'tenant_id' => null],
        ]);
    }

    public function testIndexSuccess(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get('/admin/users');

        $response->assertOk();
    }

    public function testShowUserSuccess(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/users/{$user->id}");

        $response->assertOk();
    }

    public function testStoreUserSuccess(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post('/admin/users', [
                'email' => 'created@lyceumalabang.edu.ph',
                'name' => 'Created User',
                'password' => 'Test1234!',
                'status' => 'active',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'created@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);
    }

    public function testUpdateStatusSuccess(): void
    {
        $user = User::factory()->active()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/users/{$user->id}/status", [
                'status' => 'disabled',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'disabled',
        ]);
    }

    public function testSelfDisableForbidden(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/users/{$this->admin->id}/status", [
                'status' => 'disabled',
            ]);

        $response->assertSessionHas('error');
    }
}
