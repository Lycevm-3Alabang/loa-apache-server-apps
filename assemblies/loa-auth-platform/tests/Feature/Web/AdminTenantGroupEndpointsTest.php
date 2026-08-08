<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantGroupEndpointsTest extends TestCase
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

    public function testTenantGroupEndpointsPageLoads(): void
    {
        $tenant = Tenant::factory()->create();
        $group = UserGroup::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$tenant->id}/groups/{$group->id}/endpoints");

        $response->assertOk();
        $response->assertSee($tenant->name);
        $response->assertSee($group->name);
    }
}
