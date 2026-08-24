<?php

namespace Tests\Feature\Web;

use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostResetRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_ORIGIN = 'https://app.example.test';
    private const USER_EMAIL = 'staff@lyceumalabang.edu.ph';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // Per-process cache so the password-reset throttle never leaks between tests.
        config(['cache.default' => 'array']);

        Tenant::create([
            'slug' => 'app',
            'name' => 'Tenant App',
            'status' => 'active',
            'redirect_origins' => [self::ALLOWED_ORIGIN],
        ]);

        $this->user = User::create([
            'email' => self::USER_EMAIL,
            'name' => 'Staff User',
            'password' => Hash::make('OldPass1'),
            'status' => 'active',
        ]);
    }

    private function requestReset(?string $redirect = null): void
    {
        Mail::fake();

        $payload = ['email' => self::USER_EMAIL];

        if ($redirect !== null) {
            $payload['redirect'] = $redirect;
        }

        $this->post('/forgot-password', $payload)->assertRedirect();
    }

    private function sentMailable(): PasswordResetMail
    {
        Mail::assertSent(PasswordResetMail::class, 1);

        $mailable = null;

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$mailable) {
            $mailable = $mail;

            return true;
        });

        return $mailable;
    }

    private function rawTokenFor(User $user, string $raw): string
    {
        // Mirror IdentityService storage: SHA-256 of the raw token.
        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $raw),
            'expires_at' => now()->addHour(),
        ]);

        return $raw;
    }

    // ─── Generation (forgot-password) ────────────────────────────────────────

    public function test_forgot_password_embeds_allowlisted_redirect_in_email_link(): void
    {
        $this->requestReset(self::ALLOWED_ORIGIN.'/login');

        $html = $this->sentMailable()->render();

        $this->assertStringContainsString(urlencode(self::ALLOWED_ORIGIN.'/login'), $html);
    }

    public function test_forgot_password_drops_non_allowlisted_redirect(): void
    {
        $this->requestReset('https://evil.example.test/phish');

        $html = $this->sentMailable()->render();

        $this->assertStringNotContainsString('redirect=', $html);
    }

    public function test_forgot_password_without_redirect_omits_parameter(): void
    {
        $this->requestReset(null);

        $html = $this->sentMailable()->render();

        $this->assertStringNotContainsString('redirect=', $html);
    }

    public function test_api_forgot_password_passes_allowlisted_redirect_through(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => self::USER_EMAIL,
            'redirect' => self::ALLOWED_ORIGIN.'/login',
        ])->assertOk();

        $html = $this->sentMailable()->render();

        $this->assertStringContainsString(urlencode(self::ALLOWED_ORIGIN.'/login'), $html);
    }

    // ─── Reset form carry-through ────────────────────────────────────────────

    public function test_reset_form_carries_redirect_hidden_field(): void
    {
        $response = $this->get('/reset-password?'.http_build_query([
            'token' => 't',
            'email' => self::USER_EMAIL,
            'redirect' => self::ALLOWED_ORIGIN.'/login',
        ]));

        $response->assertOk();
        $response->assertSee(self::ALLOWED_ORIGIN.'/login', false);
    }

    // ─── Consumption (post-reset redirect) ───────────────────────────────────

    public function test_successful_reset_redirects_to_allowlisted_app(): void
    {
        $raw = $this->rawTokenFor($this->user, bin2hex(random_bytes(16)));

        $response = $this->post('/reset-password', [
            'token' => $raw,
            'email' => self::USER_EMAIL,
            'redirect' => self::ALLOWED_ORIGIN.'/login',
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertRedirect(self::ALLOWED_ORIGIN.'/login');
        $this->assertTrue(Hash::check('NewPass1', $this->user->fresh()->password));
    }

    public function test_successful_reset_with_foreign_redirect_falls_back_to_login(): void
    {
        $raw = $this->rawTokenFor($this->user, bin2hex(random_bytes(16)));

        $response = $this->post('/reset-password', [
            'token' => $raw,
            'email' => self::USER_EMAIL,
            'redirect' => 'https://evil.example.test/phish',
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Password updated. Please sign in.');
    }

    public function test_successful_reset_without_redirect_falls_back_to_login(): void
    {
        $raw = $this->rawTokenFor($this->user, bin2hex(random_bytes(16)));

        $response = $this->post('/reset-password', [
            'token' => $raw,
            'email' => self::USER_EMAIL,
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_failed_reset_stays_on_form_and_keeps_redirect(): void
    {
        $raw = $this->rawTokenFor($this->user, bin2hex(random_bytes(16)));

        $response = $this->post('/reset-password', [
            'token' => 'definitely-wrong-token',
            'email' => self::USER_EMAIL,
            'redirect' => self::ALLOWED_ORIGIN.'/login',
            'password' => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);

        $response->assertStatus(302)->assertSessionHasErrors(['token']);
        $this->assertTrue(Hash::check('OldPass1', $this->user->fresh()->password));
    }
}
