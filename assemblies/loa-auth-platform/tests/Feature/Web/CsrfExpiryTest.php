<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * web-ui.md §4.0 — expired/absent CSRF sessions on auth forms must re-render
 * the originating form with a fresh token, never the raw 419 error page.
 * Unlike SsoAuthTest, the CSRF middleware is intentionally left ENABLED here.
 *
 * RefreshDatabase is required: the 419 handler and login/forgot flows query
 * users/tenants, which only exist on a migrated (isolated :memory:) database.
 */
class CsrfExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_post_without_session_rerenders_fresh_form(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@lyceumalabang.edu.ph',
            'password' => 'WrongPass1',
        ]);

        $response->assertStatus(302);
        $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'));

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Your session has expired. Please try again.');
        $response->assertSee('Sign in', false);
    }

    public function test_sso_login_post_without_session_redirects_to_sso_form(): void
    {
        $response = $this->post('/sso/login', [
            'email' => 'nobody@lyceumalabang.edu.ph',
            'password' => 'WrongPass1',
            'redirect' => 'https://e-cert.vercel.app',
        ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('/sso/login', (string) $response->headers->get('Location'));
    }

    public function test_forgot_password_post_without_session_redirects_to_forgot_form(): void
    {
        config(['cache.default' => 'array']);

        $response = $this->post('/forgot-password', [
            'email' => 'nobody@lyceumalabang.edu.ph',
        ]);

        $response->assertStatus(302);
        $this->assertStringEndsWith('/forgot-password', (string) $response->headers->get('Location'));
    }

    public function test_reset_password_post_without_session_carries_token_and_email(): void
    {
        $response = $this->post('/reset-password', [
            'token' => 'raw-token-value',
            'email' => 'staff@lyceumalabang.edu.ph',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);

        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/reset-password?', $location);
        $this->assertStringContainsString('token=raw-token-value', $location);
        $this->assertStringContainsString('email=staff%40lyceumalabang.edu.ph', $location);
    }
}
