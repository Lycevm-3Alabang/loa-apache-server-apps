<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class LoginTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testLoginSuccess(): void
    {
        User::factory()->create([
            'email' => 'login@loa.edu.ph',
            'password' => bcrypt('Test1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@loa.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token', 'refresh_token', 'token_type', 'expires_in',
            ]);
    }

    public function testLoginInvalidCredentials(): void
    {
        User::factory()->create([
            'email' => 'login@loa.edu.ph',
            'password' => bcrypt('Test1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@loa.edu.ph',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
    }

    public function testLoginDisabledAccount(): void
    {
        User::factory()->disabled()->create([
            'email' => 'disabled@loa.edu.ph',
            'password' => bcrypt('Test1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@loa.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertStatus(403);
    }

    public function testLoginLockedAccount(): void
    {
        User::factory()->locked()->create([
            'email' => 'locked@loa.edu.ph',
            'password' => bcrypt('Test1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked@loa.edu.ph',
            'password' => 'Test1234',
        ]);

        $response->assertStatus(423);
    }

    public function testLoginRecordsAttempt(): void
    {
        $user = User::factory()->create([
            'email' => 'attempt@loa.edu.ph',
            'password' => bcrypt('Test1234'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'attempt@loa.edu.ph',
            'password' => 'Test1234',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'user_id' => $user->id,
            'success' => true,
        ]);
    }

    public function testLoginMissingFields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
