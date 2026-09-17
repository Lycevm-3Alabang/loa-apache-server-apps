<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewLayoutTest extends TestCase
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

    public function test_redirect_view_renders_with_redirect_url(): void
    {
        $response = $this->withSession(['redirect_url' => 'https://e-cert.vercel.app'])
            ->get('/redirect');

        $response->assertStatus(200)
            ->assertSee('Redirecting to')
            ->assertSee('https://e-cert.vercel.app')
            ->assertSee('Continue to application');
    }

    public function test_redirect_view_without_session_redirects_to_login(): void
    {
        $response = $this->get('/redirect');

        $response->assertRedirect(route('login'));
    }

    public function test_redirect_view_renders_admin_flag_from_payload(): void
    {
        $key = bin2hex(random_bytes(32));
        config(['auth-web.encryption_key' => $key]);

        $encrypted = app(\App\Services\EncryptionService::class)->encrypt([
            'access_token' => 'test-token',
            'is_admin' => true,
        ]);

        $response = $this->withSession([
            'redirect_url' => 'https://e-cert.vercel.app',
            'redirect_payload' => $encrypted,
        ])->get('/redirect');

        $response->assertStatus(200)
            ->assertSee('Back to Admin Console')
            ->assertSee('https://e-cert.vercel.app');
    }

    public function test_redirect_view_hides_admin_flag_without_payload(): void
    {
        $response = $this->withSession(['redirect_url' => 'https://e-cert.vercel.app'])
            ->get('/redirect');

        $response->assertStatus(200)
            ->assertDontSee('Back to Admin Console');
    }

    public function test_forgot_password_view_renders_form(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200)
            ->assertSee('Reset your password')
            ->assertSee('Enter your account email and we will send a secure reset link if the account exists.')
            ->assertSee('Email address')
            ->assertSee('Send recovery link');
    }

    public function test_forgot_password_view_shows_login_back_link_without_valid_redirect(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200)
            ->assertSee('Back to sign in');
    }

    public function test_forgot_password_view_shows_back_link_with_valid_redirect(): void
    {
        Tenant::create([
            'slug' => 'loa',
            'name' => 'LOA Certificates',
            'status' => 'active',
            'app_url' => 'https://e-cert.vercel.app',
            'redirect_origins' => ['https://e-cert.vercel.app'],
        ]);

        $response = $this->get('/forgot-password?redirect=https://e-cert.vercel.app');

        $response->assertStatus(200)
            ->assertSee('Back to referrer');
    }
}
