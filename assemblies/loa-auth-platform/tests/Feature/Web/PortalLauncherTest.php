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
        $this->get('/launcher')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_launcher_lists_tenant_memberships_with_account_tile(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $cert = $this->tenant('loa', 'LOA Certificates');
        $consult = $this->tenant('consult', 'Consult Platform');
        $user->tenants()->attach([$cert->id, $consult->id]);

        $response = $this->actingAs($user, 'web')->get('/launcher');

        $response->assertStatus(200);
        $response->assertSee('LOA Certificates');
        $response->assertSee('Consult Platform');
        $response->assertSee('Account');
        $response->assertDontSee('Auth Admin Console');
    }

    public function test_launcher_shows_admin_console_tile_for_platform_admins(): void
    {
        $group = UserGroup::create([
            'name' => config('auth-web.admin_group', 'loa-auth-admin'),
        ]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->userGroups()->attach($group->id);

        // No tenant memberships: tiles must still include Console + Account.
        $response = $this->actingAs($admin, 'web')->get('/launcher');

        $response->assertStatus(200);
        $response->assertSee('Auth Admin Console');
        $response->assertSee('Account');
    }

    public function test_launcher_shows_empty_state_without_memberships(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'web')->get('/launcher');

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

        $response->assertRedirect(route('portal.launcher'));
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

    // ─── Change password (unified-auth-flow.md §9) ───────────────────────────

    public function test_account_password_change_succeeds(): void
    {
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('OldPass1'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->post('/account/password', [
            'current_password' => 'OldPass1',
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Password updated.');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NewPass1', $user->fresh()->password)
        );
    }

    public function test_account_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('OldPass1'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->post('/account/password', [
            'current_password' => 'WrongPass1',
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('OldPass1', $user->fresh()->password)
        );
    }

    public function test_account_password_change_enforces_password_policy(): void
    {
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('OldPass1'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->post('/account/password', [
            'current_password' => 'OldPass1',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('password');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('OldPass1', $user->fresh()->password)
        );
    }

    public function test_account_password_change_requires_authentication(): void
    {
        $this->from('/account')
            ->post('/account/password', [
                'current_password' => 'OldPass1',
                'password' => 'NewPass1',
                'password_confirmation' => 'NewPass1',
            ])
            ->assertRedirect('/login');
    }
}
