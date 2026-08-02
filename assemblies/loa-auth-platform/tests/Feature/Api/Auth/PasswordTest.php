<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class PasswordTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testUpdatePasswordSuccess(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPass123'),
        ]);

        $response = $this->putJson('/api/v1/auth/password', [
            'old_password' => 'OldPass123',
            'new_password' => 'NewPass456',
        ], $this->jwtHeaders($user));

        $response->assertOk()
            ->assertJsonPath('message', 'Password updated');
    }

    public function testUpdatePasswordWrongOld(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CorrectPass1'),
        ]);

        $response = $this->putJson('/api/v1/auth/password', [
            'old_password' => 'WrongPass999',
            'new_password' => 'NewPass456',
        ], $this->jwtHeaders($user));

        $response->assertStatus(400);
    }

    public function testUpdatePasswordNoAuth(): void
    {
        $response = $this->putJson('/api/v1/auth/password', [
            'old_password' => 'OldPass123',
            'new_password' => 'NewPass456',
        ]);

        $response->assertStatus(401);
    }

    public function testUpdatePasswordValidationFails(): void
    {
        $user = User::factory()->create();

        $response = $this->putJson('/api/v1/auth/password', [
            'old_password' => '',
            'new_password' => 'short',
        ], $this->jwtHeaders($user));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['old_password', 'new_password']);
    }
}
