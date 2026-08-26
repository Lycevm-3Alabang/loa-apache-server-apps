<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * dashboard-account.md §11 checklist: root router, /health, launcher alias,
 * return intent for tenant-app deep links, self-service name update and the
 * standalone change-password page.
 */
class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
    }

    private function tenant(string $slug, string $name): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => $name,
            'status' => 'active',
            'app_url' => "https://{$slug}.lyceumalabang.edu.ph",
            'redirect_origins' => ["https://{$slug}.lyceumalabang.edu.ph"],
        ]);
    }

    // ─── Root router (dashboard-account.md §3) ───────────────────────────────

    public function test_guest_root_with_redirect_query_routes_to_sso_login_preserving_query(): void
    {
        $this->get('/?redirect=https://loa.lyceumalabang.edu.ph')
            ->assertRedirect('/sso/login?redirect=https%3A%2F%2Floa.lyceumalabang.edu.ph');
    }

    public function test_health_returns_json_payload(): void
    {
        $this->get('/health')
            ->assertStatus(200)
            ->assertJson([
                'service' => 'LOA Auth Platform',
                'version' => '1.0.0',
                'status' => 'running',
            ]);
    }

    public function test_single_tenant_member_sees_dashboard_without_handoff(): void
    {
        // v1.1 D11: auto-enter removed — direct navigation never mints a
        // handoff, regardless of membership count.
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $user->tenants()->attach($cert->id);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertOk();
        $response->assertSee('LOA Certificates');
        $response->assertSee('Manage account');
        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_multi_tenant_member_sees_dashboard_with_account_summary(): void
    {
        $user = User::factory()->create([
            'email' => 'me@lyceumalabang.edu.ph',
            'name' => 'Portal Member',
            'status' => 'active',
        ]);
        $user->tenants()->attach([
            $this->tenant('loa', 'LOA Certificates')->id,
            $this->tenant('consult', 'Consult Platform')->id,
        ]);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertStatus(200);
        $response->assertSee('Portal Member');
        $response->assertSee('me@lyceumalabang.edu.ph');
        $response->assertSee('Active', false);
        $response->assertSee('Manage account');
    }

    // ─── Console access boundary (dashboard-account.md v1.1 D9/D10) ──────────

    public function test_platform_admin_sees_restricted_console_nav(): void
    {
        $group = UserGroup::create([
            'name' => config('auth-web.admin_group', 'loa-auth-admin'),
        ]);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->userGroups()->attach($group->id);

        $response = $this->actingAs($admin, 'web')->get('/');

        $response->assertOk();
        $response->assertSee(route('admin.users'));
        $response->assertSee(route('admin.tenants'));
        $response->assertSee(route('admin.audit-logs'));
        $response->assertSee(route('console.logout'));
    }

    public function test_non_admin_does_not_see_restricted_console_nav(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertOk();
        $response->assertDontSee(route('admin.users'));
        $response->assertDontSee(route('admin.tenants'));
        $response->assertDontSee(route('admin.audit-logs'));
        $response->assertSee(route('console.logout'));
    }

    public function test_non_admin_still_gets_403_on_admin_sections(): void
    {
        // D9: hiding the nav never grants a route; only platform-admin group
        // membership passes web.admin.
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user, 'web')
            ->get('/admin/users')
            ->assertStatus(403);
    }

    public function test_tenant_scoped_admin_role_confers_nothing_here(): void
    {
        // A user in a tenant-style "cert-admin" group has no weight on this
        // app (D9) — only config('auth-web.admin_group') matters.
        $group = UserGroup::create(['name' => 'cert-admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->userGroups()->attach($group->id);

        $this->actingAs($user, 'web')
            ->get('/admin/users')
            ->assertStatus(403);

        $response = $this->actingAs($user, 'web')->get('/');
        $response->assertDontSee(route('admin.users'));
    }

    public function test_console_logout_signs_out_any_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'web')->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_console_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_root_with_valid_redirect_enters_target_tenant_for_member(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $user->tenants()->attach($cert->id);

        $this->actingAs($user, 'web')
            ->get('/?redirect=https://loa.lyceumalabang.edu.ph')
            ->assertRedirect('/redirect');
    }

    // ─── Return intent (dashboard-account.md §6) ─────────────────────────────

    public function test_guest_password_page_captures_return_intent(): void
    {
        $this->get('/account/password')
            ->assertRedirect('/login');

        $this->assertSame('/account/password', $this->app['session']->get('return_to'));
    }

    public function test_login_returns_user_to_captured_intent(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);
        $cert = $this->tenant('consult', 'Consult Platform');
        $user->tenants()->attach($cert->id);

        $response = $this->withSession(['return_to' => '/account/password'])
            ->post('/login', [
                'email' => 'staff@lyceumalabang.edu.ph',
                'password' => 'Test1234',
            ]);

        $response->assertRedirect('/account/password');
        $this->assertNull($this->app['session']->get('return_to'));
    }

    public function test_explicit_redirect_target_outranks_return_intent(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);
        $cert = $this->tenant('consult', 'Consult Platform');
        $user->tenants()->attach($cert->id);

        $response = $this->withSession(['return_to' => '/account/password'])
            ->post('/sso/login', [
                'email' => 'staff@lyceumalabang.edu.ph',
                'password' => 'Test1234',
                'redirect' => 'https://consult.lyceumalabang.edu.ph',
            ]);

        $response->assertRedirect('/redirect');
        $this->assertNull($this->app['session']->get('return_to'));
    }

    public function test_malicious_return_intent_is_ignored(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);
        $cert = $this->tenant('consult', 'Consult Platform');
        $user->tenants()->attach($cert->id);

        foreach (['//evil.com', 'https://evil.com', '/\\evil.com'] as $badIntent) {
            // Invalid intents are discarded and normal routing applies
            // (v1.1 D11: dashboard, no handoff).
            $this->withSession(['return_to' => $badIntent])
                ->post('/login', [
                    'email' => 'staff@lyceumalabang.edu.ph',
                    'password' => 'Test1234',
                ])
                ->assertRedirect(route('home'));
        }
    }

    // ─── Account rework (dashboard-account.md §5) ────────────────────────────

    public function test_account_page_hides_password_form_and_offers_link(): void
    {
        $user = User::factory()->create([
            'email' => 'me@lyceumalabang.edu.ph',
            'name' => 'Portal Member',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->get('/account');

        $response->assertStatus(200);
        $response->assertSee('me@lyceumalabang.edu.ph');
        $response->assertSee('Change password');
        $response->assertDontSee('name="current_password"', false);
        $response->assertDontSee('name="password"', false);
    }

    public function test_name_edit_reveals_input_and_saves(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'status' => 'active']);

        $this->actingAs($user, 'web')
            ->get('/account?edit=name')
            ->assertStatus(200)
            ->assertSee('name="name"', false);

        $response = $this->actingAs($user, 'web')->post('/account/name', [
            'name' => '  New Name  ',
        ]);

        $response->assertRedirect(route('portal.account'));
        $response->assertSessionHas('status', 'Name updated.');
        $this->assertSame('New Name', $user->fresh()->name);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.profile.name_update',
        ]);
    }

    public function test_name_update_rejects_blank_and_oversized_values(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'status' => 'active']);

        $this->actingAs($user, 'web')
            ->post('/account/name', ['name' => '   '])
            ->assertSessionHasErrors('name');
        $this->actingAs($user, 'web')
            ->post('/account/name', ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');

        $this->assertSame('Old Name', $user->fresh()->name);
    }

    public function test_name_update_requires_authentication(): void
    {
        $this->post('/account/name', ['name' => 'Nope'])
            ->assertRedirect('/login');
    }
}
