<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SsoAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $this->tenant = Tenant::create([
            'slug' => 'loa',
            'name' => 'LOA Certificates',
            'status' => 'active',
            'app_url' => 'https://e-cert.vercel.app',
            'redirect_origins' => ['https://e-cert.vercel.app'],
        ]);
    }

    private function memberUser(): User
    {
        $user = User::factory()->create([
            'email' => 'staff@lyceumalabang.edu.ph',
            'name' => 'Staff User',
            'status' => 'active',
        ]);
        $user->tenants()->attach($this->tenant->id);

        return $user;
    }

    private function adminUser(): User
    {
        $group = UserGroup::create([
            'name' => config('auth-web.admin_group', 'loa-auth-admin'),
        ]);

        $user = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'status' => 'active',
        ]);

        $user->userGroups()->attach($group->id);

        return $user;
    }

    public function test_get_sso_login_renders_form(): void
    {
        $response = $this->get('/sso/login?redirect=https://e-cert.vercel.app');

        $response->assertStatus(200)
            ->assertSee('Sign in to LOA Platform');
    }

    public function test_sso_login_success_redirects_to_splash(): void
    {
        $this->memberUser();

        $response = $this->post('/sso/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertRedirect('/redirect');

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => User::where('email', 'staff@lyceumalabang.edu.ph')->first()->id,
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email_attempted' => 'staff@lyceumalabang.edu.ph',
            'success' => 1,
        ]);
    }

    public function test_sso_login_success_uses_encrypted_payload_when_configured(): void
    {
        $this->memberUser();

        $key = bin2hex(random_bytes(32));
        config(['auth-web.encryption_key' => $key]);

        $response = $this->post('/sso/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertRedirect('/redirect');

        $splash = $this->get('/redirect');

        $splash->assertStatus(200);
        $splash->assertSee('https://e-cert.vercel.app');

        preg_match('/href="[^"]*#payload=([^"]+)"/', $splash->getContent(), $matches);

        $this->assertCount(2, $matches);

        $payload = app(\App\Services\EncryptionService::class)->decrypt($matches[1]);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('access_token', $payload);
        $this->assertSame('staff@lyceumalabang.edu.ph', $payload['user']['email']);
        $this->assertSame('loa', $payload['tenant']['slug']);
    }

    public function test_sso_login_admits_admin_with_tenant_membership(): void
    {
        $admin = $this->adminUser();
        $admin->tenants()->attach($this->tenant->id);

        $response = $this->post('/sso/login', [
            'email' => 'admin@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertRedirect('/redirect');
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_sso_login_denies_admin_without_membership_via_launcher(): void
    {
        $this->adminUser();

        $response = $this->post('/sso/login', [
            'email' => 'admin@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error');

        $admin = User::where('email', 'admin@lyceumalabang.edu.ph')->first();

        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $admin->id,
            'revoked_at' => null,
        ]);
    }

    public function test_sso_login_rejects_without_redirect(): void
    {
        $this->memberUser();

        $response = $this->post('/sso/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('credentials');
    }

    public function test_sso_login_rejects_unknown_redirect_origin(): void
    {
        $this->memberUser();

        $response = $this->post('/sso/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://evil.example.com',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('credentials');
    }

    public function test_sso_login_sends_non_member_to_launcher(): void
    {
        User::factory()->create([
            'email' => 'outsider@lyceumalabang.edu.ph',
            'name' => 'Outsider',
            'status' => 'active',
        ]);

        $response = $this->post('/sso/login', [
            'email' => 'outsider@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        // Valid origin but no membership - dashboard with a denial flash,
        // tokens revoked (unified-auth-flow.md §10).
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error');

        $user = User::where('email', 'outsider@lyceumalabang.edu.ph')->first();

        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_sso_login_invalid_credentials(): void
    {
        $this->memberUser();

        $response = $this->post('/sso/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'WrongPassword',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('credentials');
    }

    public function test_login_lands_admin_on_launcher_with_web_session(): void
    {
        $this->adminUser();

        $response = $this->post('/login', [
            'email' => 'admin@lyceumalabang.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertTrue(Auth::guard('web')->check());

        $admin = User::where('email', 'admin@lyceumalabang.edu.ph')->first();

        $this->assertDatabaseMissing('refresh_tokens', [
            'user_id' => $admin->id,
            'revoked_at' => null,
        ]);
    }

    public function test_login_delivers_tokens_for_tenant_member_with_valid_redirect(): void
    {
        $this->memberUser();

        $response = $this->post('/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertRedirect('/redirect');
        $this->assertSame('https://e-cert.vercel.app', $this->app['session']->get('redirect_url'));
        $this->assertTrue(Auth::guard('web')->check());

        $user = User::where('email', 'staff@lyceumalabang.edu.ph')->first();

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_login_lands_single_tenant_member_on_dashboard_without_redirect(): void
    {
        $this->memberUser();

        // No redirect intent (dashboard-account.md v1.1 D11): everyone lands
        // on the console dashboard; no handoff is minted.
        $response = $this->post('/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertNull($this->app['session']->get('redirect_url'));
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_login_drops_unknown_redirect_origin_and_lands_on_dashboard(): void
    {
        $this->memberUser();

        $response = $this->post('/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://evil.example.com',
        ]);

        // The invalid origin is discarded; v1.1 D11 routes to the dashboard.
        $response->assertRedirect(route('home'));
        $this->assertNull($this->app['session']->get('redirect_url'));
    }

    public function test_login_lands_multi_app_member_on_launcher(): void
    {
        $member = $this->memberUser();

        $second = Tenant::create([
            'slug' => 'consult',
            'name' => 'Consult Platform',
            'status' => 'active',
            'app_url' => 'https://consult.lyceumalabang.edu.ph',
            'redirect_origins' => ['https://consult.lyceumalabang.edu.ph'],
        ]);
        $member->tenants()->attach($second->id);

        $response = $this->post('/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertRedirect(route('home'));
    }

    public function test_splash_redirects_to_login_without_session(): void
    {
        $response = $this->get('/redirect');

        $response->assertRedirect('/login');
    }

    public function test_splash_is_one_time_use(): void
    {
        $this->memberUser();

        $this->post('/sso/login', [
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $this->assertNotNull($this->app['session']->get('redirect_url'));

        $this->get('/redirect')->assertStatus(200);

        $this->assertNull($this->app['session']->get('redirect_url'));

        $this->get('/redirect')->assertRedirect('/login');
    }

    public function test_get_sso_register_renders_form(): void
    {
        $response = $this->get('/sso/register');

        $response->assertStatus(200)
            ->assertSee('Create your LOA account');
    }

    public function test_sso_register_loa_domain_success(): void
    {
        $response = $this->post('/sso/register', [
            'name' => 'New User',
            'email' => 'new@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'password_confirmation' => 'Test1234',
        ]);

        $response->assertRedirect('/sso/login');
        $response->assertSessionHas('status', 'Account created. Please sign in.');

        $this->assertDatabaseHas('users', [
            'email' => 'new@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);
    }

    public function test_sso_register_allows_itm_domain(): void
    {
        $response = $this->post('/sso/register', [
            'name' => 'New User',
            'email' => 'new@itmlyceumalabang.onmicrosoft.com',
            'password' => 'Test1234',
            'password_confirmation' => 'Test1234',
        ]);

        $response->assertRedirect('/sso/login');
    }

    public function test_sso_register_rejects_external_domain(): void
    {
        $response = $this->post('/sso/register', [
            'name' => 'New User',
            'email' => 'new@gmail.com',
            'password' => 'Test1234',
            'password_confirmation' => 'Test1234',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'new@gmail.com',
        ]);
    }

    public function test_sso_register_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'taken@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);

        $response = $this->post('/sso/register', [
            'name' => 'Duplicate',
            'email' => 'taken@lyceumalabang.edu.ph',
            'password' => 'Test1234',
            'password_confirmation' => 'Test1234',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }
}
