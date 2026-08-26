<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_single_tenant_member_auto_enters_from_root(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $user->tenants()->attach($cert->id);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertRedirect('/redirect');
        $this->assertSame(
            'https://loa.lyceumalabang.edu.ph',
            $this->app['session']->get('redirect_url')
        );
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
            $this->withSession(['return_to' => $badIntent])
                ->post('/login', [
                    'email' => 'staff@lyceumalabang.edu.ph',
                    'password' => 'Test1234',
                ])
                ->assertRedirect('/redirect');
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
