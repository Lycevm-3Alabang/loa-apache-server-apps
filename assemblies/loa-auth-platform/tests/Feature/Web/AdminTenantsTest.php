<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'status' => 'active',
        ]);

        $adminGroup = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators'],
        );
        $this->admin->userGroups()->attach($adminGroup);
    }

    public function testAdminCanListTenants(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/tenants');

        $response->assertOk();
    }

    public function testAdminCanShowCreateTenantForm(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/tenants/create');

        $response->assertOk();
    }
}
