<?php

namespace Tests\Feature\Web\SetPassword;

use App\Models\PasswordSetToken;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CertActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $certPlatformUrl = 'http://localhost:9001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        config(['cert-platform.base_url' => $this->certPlatformUrl]);
    }

    public function test_redirects_to_login_with_missing_cert_param(): void
    {
        $response = $this->get('/set-password/cert?email=user@example.com');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Invalid activation link.');
    }

    public function test_redirects_to_login_with_missing_email_param(): void
    {
        $response = $this->get('/set-password/cert?cert=CERT-001');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Invalid activation link.');
    }

    public function test_redirects_to_login_with_invalid_email_format(): void
    {
        $response = $this->get('/set-password/cert?cert=CERT-001&email=not-an-email');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Invalid activation link.');
    }

    public function test_redirects_to_login_when_cert_not_found(): void
    {
        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'message' => 'Certificate not found',
            ], 404),
        ]);

        $response = $this->get('/set-password/cert?cert=CERT-9999&email=user@example.com');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Invalid or expired certificate.');
    }

    public function test_redirects_to_login_when_cert_expired(): void
    {
        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'data' => [
                    'certificate_number' => 'CERT-001',
                    'recipient_email' => 'user@example.com',
                    'status' => 'expired',
                ],
            ], 200),
        ]);

        $response = $this->get('/set-password/cert?cert=CERT-001&email=user@example.com');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Invalid or expired certificate.');
    }

    public function test_redirects_to_login_when_email_mismatch(): void
    {
        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'data' => [
                    'certificate_number' => 'CERT-001',
                    'recipient_email' => 'other@example.com',
                    'status' => 'active',
                ],
            ], 200),
        ]);

        $response = $this->get('/set-password/cert?cert=CERT-001&email=user@example.com');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Invalid or expired certificate.');
    }

    public function test_redirects_to_sso_when_user_already_exists(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'data' => [
                    'certificate_number' => 'CERT-001',
                    'recipient_email' => 'existing@example.com',
                    'status' => 'active',
                ],
            ], 200),
        ]);

        $response = $this->get('/set-password/cert?cert=CERT-001&email=existing@example.com');

        $response->assertRedirect(route('sso.login'));
        $response->assertSessionHas('status', 'You already have an account. Please sign in.');
    }

    public function test_shows_confirmation_page_for_new_user(): void
    {
        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'data' => [
                    'certificate_number' => 'CERT-001',
                    'recipient_name' => 'Juan Dela Cruz',
                    'recipient_email' => 'newuser@example.com',
                    'status' => 'active',
                ],
            ], 200),
        ]);

        $response = $this->get('/set-password/cert?cert=CERT-001&email=newuser@example.com');

        $response->assertStatus(200);
        $response->assertViewIs('auth.cert-activate');
        $response->assertViewHas([
            'certificateNumber' => 'CERT-001',
            'recipientName' => 'Juan Dela Cruz',
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_creates_user_in_transaction(): void
    {
        $tenant = Tenant::create([
            'name' => 'LOA e-Cert',
            'slug' => 'loa-e-cert',
        ]);

        $group = UserGroup::create([
            'name' => 'cert-user',
            'tenant_id' => $tenant->id,
        ]);

        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'data' => [
                    'certificate_number' => 'CERT-001',
                    'recipient_name' => 'Juan Dela Cruz',
                    'recipient_email' => 'newuser@example.com',
                    'status' => 'active',
                ],
            ], 200),
        ]);

        $response = $this->post('/set-password/cert', [
            'cert' => 'CERT-001',
            'email' => 'newuser@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.set-password');
        $response->assertViewHas('token');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'status' => 'pending',
        ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $group->id,
        ]);
        $this->assertDatabaseHas('password_set_tokens', [
            'user_id' => $user->id,
        ]);
    }

    public function test_handles_missing_tenant_gracefully(): void
    {
        Http::fake([
            "{$this->certPlatformUrl}/api/v1/verify/*" => Http::response([
                'data' => [
                    'certificate_number' => 'CERT-001',
                    'recipient_name' => 'Juan Dela Cruz',
                    'recipient_email' => 'newuser@example.com',
                    'status' => 'active',
                ],
            ], 200),
        ]);

        $response = $this->post('/set-password/cert', [
            'cert' => 'CERT-001',
            'email' => 'newuser@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.set-password');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'status' => 'pending',
        ]);
    }
}
