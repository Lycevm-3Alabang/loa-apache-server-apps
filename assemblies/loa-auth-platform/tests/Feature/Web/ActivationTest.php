<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationTest extends TestCase
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

    public function testActivateUserSuccess(): void
    {
        $user = User::factory()->create([
            'email' => 'pending@lyceumalabang.edu.ph',
            'name' => 'Pending User',
            'status' => 'pending',
        ]);

        $activationService = app(\App\Services\ActivationService::class);
        $rawToken = $activationService->createActivation($user);

        $response = $this->post('/activate', [
            'token' => $rawToken,
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);

        // Activation lands on the console dashboard (dashboard-account.md
        // v1.1 D11) — no auto-enter, regardless of membership count.
        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);

        // Verify password was set
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Test1234!', $user->fresh()->password));
    }

    public function testActivateUserInvalidToken(): void
    {
        $response = $this->post('/activate', [
            'token' => 'invalid-token',
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('token');
    }

    public function testActivateUserExpiredToken(): void
    {
        $user = User::factory()->create([
            'email' => 'expired@lyceumalabang.edu.ph',
            'name' => 'Expired User',
            'status' => 'pending',
        ]);

        \App\Models\Activation::create([
            'user_id' => $user->id,
            'token' => hash('sha256', 'valid-token'),
            'expires_at' => now()->subHours(25),
        ]);

        $response = $this->post('/activate', [
            'token' => 'valid-token',
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('token');
    }

    public function testShowActivateWithInvalidToken(): void
    {
        $response = $this->get('/activate?token=invalid-token');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
    }

    public function testShowActivateWithValidToken(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@lyceumalabang.edu.ph',
            'name' => 'Valid User',
            'status' => 'pending',
        ]);

        $activationService = app(\App\Services\ActivationService::class);
        $rawToken = $activationService->createActivation($user);

        $response = $this->get("/activate?token={$rawToken}");

        $response->assertStatus(200);
        $response->assertViewIs('activate');
        $response->assertViewHas(['email', 'token']);
    }
}
