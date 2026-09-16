<?php

namespace Tests\Feature\Api\Users;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Tenant;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for GET /api/v1/users/{sub} visibility across tenant contexts.
 *
 * Simulates the Cert → Auth flow: Cert forwards its JWT (which carries a
 * tenant claim) to Auth's GET /api/v1/users/{sub}. Auth's show() reads
 * tenant.id from the JWT and checks userInTenant().
 *
 * Scenarios:
 * 1. Platform admin + cert tenant member  → 200
 * 2. Platform admin + cert-staff member   → 200
 * 3. Platform admin, no tenant membership  → 200 (platform admin is visible)
 * 4. cert-admin only (no platform admin)   → 200
 * 5. User in tenant, no special groups     → 200
 * 6. User exists but NOT in tenant         → 404
 * 7. Nonexistent user                      → 404
 * 8. Disabled platform admin without tenant → 200 (status shown)
 * 9. Locked platform admin without tenant  → 200 (status shown)
 */
class UserShowTenantVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'E-Cert',
            'slug' => 'loa-e-cert',
        ]);
    }

    private function platformAdminGroup(): UserGroup
    {
        return UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators']
        );
    }

    private function certAdminGroup(): UserGroup
    {
        return UserGroup::firstOrCreate(
            ['name' => 'cert-admin'],
            ['description' => 'Cert administrators']
        );
    }

    private function certStaffGroup(): UserGroup
    {
        return UserGroup::firstOrCreate(
            ['name' => 'cert-staff'],
            ['description' => 'Cert staff']
        );
    }

    /**
     * Generate a JWT that carries a tenant claim, simulating a Cert-to-Auth
     * call. The requesting user must have users.view permission.
     */
    private function jwtWithTenant(string $tenantId): array
    {
        $admin = User::factory()->create();
        $adminGroup = $this->platformAdminGroup();
        $admin->userGroups()->syncWithoutDetaching([$adminGroup->id]);

        $viewPerm = \App\Models\Permission::firstOrCreate(
            ['key' => 'users.view'],
            ['description' => 'View users']
        );
        $adminGroup->permissions()->syncWithoutDetaching([$viewPerm->id]);

        $jwt = app(JWTService::class);
        $token = $jwt->generateAccessToken([
            'sub' => $admin->id,
            'email' => $admin->email,
            'name' => $admin->name,
            'groups' => [$adminGroup->name],
            'permissions' => ['users.view'],
            'tenant' => ['id' => $tenantId, 'slug' => 'loa-e-cert'],
        ]);

        return ['Authorization' => "Bearer $token"];
    }

    // ------------------------------------------------------------------
    // Scenario 1: Platform admin + cert tenant member → 200
    // ------------------------------------------------------------------

    public function test_show_platform_admin_with_cert_admin_membership(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->userGroups()->syncWithoutDetaching([$this->platformAdminGroup()->id]);
        $user->userGroups()->syncWithoutDetaching([$this->certAdminGroup()->id]);
        $user->tenants()->attach($this->tenant->id);

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('status', 'active');
    }

    // ------------------------------------------------------------------
    // Scenario 2: Platform admin + cert-staff member → 200
    // ------------------------------------------------------------------

    public function test_show_platform_admin_with_cert_staff_membership(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->userGroups()->syncWithoutDetaching([$this->platformAdminGroup()->id]);
        $user->userGroups()->syncWithoutDetaching([$this->certStaffGroup()->id]);
        $user->tenants()->attach($this->tenant->id);

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'active');
    }

    // ------------------------------------------------------------------
    // Scenario 3: Platform admin, NO tenant membership → 200
    //   This is the critical fix: previously returned 404.
    // ------------------------------------------------------------------

    public function test_show_platform_admin_without_tenant_membership(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->userGroups()->syncWithoutDetaching([$this->platformAdminGroup()->id]);
        // No tenants()->attach — user has no tenant membership

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        // Before fix: 404. After fix: 200 — platform admins are visible.
        $response->assertOk()
            ->assertJsonPath('status', 'active');
    }

    // ------------------------------------------------------------------
    // Scenario 4: cert-admin only (no platform admin) → 200
    // ------------------------------------------------------------------

    public function test_show_cert_admin_without_platform_admin(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->userGroups()->syncWithoutDetaching([$this->certAdminGroup()->id]);
        $user->tenants()->attach($this->tenant->id);

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'active');
    }

    // ------------------------------------------------------------------
    // Scenario 5: User in tenant, no special groups → 200
    // ------------------------------------------------------------------

    public function test_show_regular_user_in_tenant(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($this->tenant->id);

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'active');
    }

    // ------------------------------------------------------------------
    // Scenario 6: User exists but NOT in tenant → 404
    // ------------------------------------------------------------------

    public function test_show_user_not_in_tenant(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        // No tenant attachment and no platform admin group

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Scenario 7: Nonexistent user → 404
    // ------------------------------------------------------------------

    public function test_show_nonexistent_user(): void
    {
        $response = $this->getJson(
            '/api/v1/users/00000000-0000-0000-0000-000000000000',
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Scenario: Disabled platform admin without tenant → 200 (shows status)
    // ------------------------------------------------------------------

    public function test_show_disabled_platform_admin_without_tenant(): void
    {
        $user = User::factory()->create(['status' => 'disabled']);
        $user->userGroups()->syncWithoutDetaching([$this->platformAdminGroup()->id]);

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'disabled');
    }

    // ------------------------------------------------------------------
    // Scenario: Locked platform admin without tenant → 200 (shows status)
    // ------------------------------------------------------------------

    public function test_show_locked_platform_admin_without_tenant(): void
    {
        $user = User::factory()->create(['status' => 'locked']);
        $user->userGroups()->syncWithoutDetaching([$this->platformAdminGroup()->id]);

        $response = $this->getJson(
            "/api/v1/users/{$user->id}",
            $this->jwtWithTenant($this->tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('status', 'locked');
    }
}
