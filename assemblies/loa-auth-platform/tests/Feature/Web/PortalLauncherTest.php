<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLauncherTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/launcher')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_launcher_alias_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $consult = $this->tenant('consult', 'Consult Platform');
        $user->tenants()->attach([$cert->id, $consult->id]);

        $this->actingAs($user, 'web')
            ->get('/launcher')
            ->assertRedirect(route('home'));
    }

    public function test_launcher_lists_tenant_memberships_with_account_menu(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $consult = $this->tenant('consult', 'Consult Platform');
        $user->tenants()->attach([$cert->id, $consult->id]);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertStatus(200);
        $response->assertSee('LOA Certificates');
        $response->assertSee('Consult Platform');
        $response->assertSee('Manage account');
        $response->assertDontSee('Auth Admin Console');
    }

    public function test_platform_admin_gets_launcher_without_console_tile(): void
    {
        // v1.2 D13: no Auth Admin Console tile anywhere in the dashboard body,
        // not even for platform-admins — console entry lives in the topbar nav.
        $group = UserGroup::create([
            'name' => config('auth-web.admin_group', 'loa-auth-admin'),
        ]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->userGroups()->attach($group->id);

        $response = $this->actingAs($admin, 'web')->get('/');

        $response->assertStatus(200);
        $response->assertSee('Manage account');
        $response->assertDontSee('Auth Admin Console');
        $response->assertSee(route('admin.users'));
        $response->assertSee(route('admin.tenants'));
        $response->assertSee(route('admin.audit-logs'));
    }

    public function test_launcher_shows_empty_state_without_memberships(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'web')->get('/');

        $response->assertStatus(200);
        $response->assertSee("don't have access to any applications", false);
    }

    public function test_go_mints_handoff_for_member(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $user->tenants()->attach($cert->id);

        $response = $this->actingAs($user, 'web')->post("/launcher/go/{$cert->id}");

        $response->assertRedirect('/redirect');
        $this->assertSame(
            'https://loa.lyceumalabang.edu.ph',
            $this->app['session']->get('redirect_url')
        );

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_go_denies_non_member(): void
    {
        $outsider = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');

        $response = $this->actingAs($outsider, 'web')->post("/launcher/go/{$cert->id}");

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $outsider->id,
            'revoked_at' => null,
        ]);
    }

    public function test_account_shows_profile_readout(): void
    {
        $user = User::factory()->create([
            'email' => 'me@lyceumalabang.edu.ph',
            'name' => 'Portal Member',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->get('/account');

        $response->assertStatus(200);
        $response->assertSee('me@lyceumalabang.edu.ph');
        $response->assertSee('Portal Member');
    }

    // ─── Change password = emailed reset link (dashboard-account.md v1.3 D17/D18) ──

    public function test_change_password_emails_reset_link_and_stays_put(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $user = User::factory()->create([
            'email' => 'me@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')
            ->post('/account/password/email');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Reset link sent to me@lyceumalabang.edu.ph.');

        \Illuminate\Support\Facades\Mail::assertSent(
            \App\Mail\PasswordResetMail::class,
            1,
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.profile.password_reset_request',
        ]);
    }

    public function test_change_password_send_is_throttled(): void
    {
        \Illuminate\Support\Facades\RateLimiter::clear(
            'password-reset:'.hash('sha256', '|10.255.255.1')
        );

        $user = User::factory()->create(['status' => 'active']);
        $actor = $this->actingAs($user, 'web')
            ->withServerVariables(['REMOTE_ADDR' => '10.255.255.1']);

        // First request consumes the single-slot limiter (password.reset.throttle).
        $actor->post('/account/password/email')->assertRedirect();

        // Second request inside the decay window is silently capped.
        $this->actingAs($user, 'web')
            ->withServerVariables(['REMOTE_ADDR' => '10.255.255.1'])
            ->post('/account/password/email')
            ->assertRedirect(route('password.forgot'));
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->post('/account/password/email')->assertRedirect('/login');
    }

    public function test_completed_reset_signs_out_portal_session(): void
    {
        // v1.3 D18: finishing the emailed reset ends the portal session on
        // top of the refresh-token revocation done by IdentityService.
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('OldPass1'),
            'status' => 'active',
        ]);
        $token = $this->app->make(\App\Services\IdentityService::class)
            ->requestPasswordReset($user->email);
        $this->assertNotNull($token);

        $response = $this->actingAs($user, 'web')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Password updated. Please sign in.');
        $this->assertFalse(\Illuminate\Support\Facades\Auth::guard('web')->check());
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NewPass1', $user->fresh()->password)
        );
        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_guest_reset_completion_unaffected_by_d18(): void
    {
        // Regression guard: the guest forgot-password path has no portal
        // session — logout() must stay a harmless no-op there.
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('OldPass1'),
            'status' => 'active',
        ]);
        $token = $this->app->make(\App\Services\IdentityService::class)
            ->requestPasswordReset($user->email);
        $this->assertNotNull($token);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass2',
            'password_confirmation' => 'NewPass2',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Password updated. Please sign in.');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NewPass2', $user->fresh()->password)
        );
    }
}
