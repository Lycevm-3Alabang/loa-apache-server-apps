<?php

namespace Tests\Feature\Api;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LocalEmailTest extends TestCase
{
    private string $secret = 'test-secret-aces-relay-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail-relay.secret' => $this->secret]);
        config(['mail-relay.allowed_domains' => ['lyceumalabang.edu.ph', 'gmail.com']]);
        RateLimiter::clear('mail-relay');
    }

    public function test_unauthorized_on_missing_secret(): void
    {
        $response = $this->postJson('/api/v1/mail/send', $this->payload());

        $response->assertStatus(401)
            ->assertJson(['ok' => false, 'error' => 'Unauthorized.']);
    }

    public function test_unauthorized_on_wrong_secret(): void
    {
        $response = $this->postJson('/api/v1/mail/send', $this->payload(), [
            'Authorization' => 'Bearer wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_off_allowlist_domain(): void
    {
        $payload = $this->payload();
        $payload['to'] = 'someone@evil.example';

        $response = $this->postJson('/api/v1/mail/send', $payload, [
            'Authorization' => "Bearer {$this->secret}",
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('invalid.0.domain', 'evil.example');
    }

    public function test_rejects_off_allowlist_in_cc_and_bcc(): void
    {
        $payload = $this->payload();
        $payload['cc'] = ['cc@evil.example'];
        $payload['bcc'] = ['bcc@nope.test'];

        $response = $this->postJson('/api/v1/mail/send', $payload, [
            'Authorization' => "Bearer {$this->secret}",
        ]);

        $response->assertStatus(422)
            ->assertJsonCount(2, 'invalid');
    }

    public function test_validation_oversize_html(): void
    {
        $payload = $this->payload();
        $payload['html'] = str_repeat('a', 204801);

        $response = $this->postJson('/api/v1/mail/send', $payload, [
            'Authorization' => "Bearer {$this->secret}",
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_validation_bad_email(): void
    {
        $payload = $this->payload();
        $payload['to'] = 'not-an-email';

        $response = $this->postJson('/api/v1/mail/send', $payload, [
            'Authorization' => "Bearer {$this->secret}",
        ]);

        $response->assertStatus(422);
    }

    public function test_string_to_is_normalized_and_sends(): void
    {
        Mail::fake();

        $payload = $this->payload();
        $payload['to'] = 'student@gmail.com';

        $response = $this->postJson('/api/v1/mail/send', $payload, [
            'Authorization' => "Bearer {$this->secret}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true);

        // NOTE: `Mail::fake` only records Mailable instances; `Mail::html`
        // (string template) is dispatched but not counted. No real transport in
        // tests, so message_id stays null and the 200 already proves success.
        $this->assertNotNull($response->json('message_id'));
    }

    public function test_happy_path_sends_one_message(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/mail/send', $this->payload(), [
            'Authorization' => "Bearer {$this->secret}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true);

        // array mailer returns a message id at runtime.
        $this->assertNotNull($response->json('message_id'));
    }

    public function test_rate_limit_returns_429_with_retry_after(): void
    {
        Cache::flush();
        RateLimiter::for('mail-relay', fn (): Limit => Limit::perMinute(1)->by('rl-test'));

        Mail::fake();

        $headers = ['Authorization' => "Bearer {$this->secret}"];

        $this->postJson('/api/v1/mail/send', $this->payload(), $headers)
            ->assertStatus(200);

        $second = $this->postJson('/api/v1/mail/send', $this->payload(), $headers);

        $second->assertStatus(429)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['retry_after']);

        $this->assertNotNull($second->headers->get('Retry-After'));

        RateLimiter::for('mail-relay', fn ($request) => Limit::perMinute(60)->by($request->bearerToken() ?: $request->ip()));
    }

    private function payload(): array
    {
        return [
            'to' => ['student@gmail.com'],
            'subject' => 'ACES test',
            'html' => '<p>Hello</p>',
        ];
    }
}
