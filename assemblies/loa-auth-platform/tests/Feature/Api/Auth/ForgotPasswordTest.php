<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\PasswordResetToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testForgotPasswordSuccess(): void
    {
        User::factory()->create(['email' => 'forgot@lyceumalabang.edu.ph']);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'forgot@lyceumalabang.edu.ph',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If the email exists, a reset link has been sent');
    }

    public function testForgotPasswordUnknownEmail(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'unknown@lyceumalabang.edu.ph',
        ]);

        $response->assertOk();
    }

    public function testForgotPasswordInvalidEmail(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function testResetPasswordSuccess(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPass123')]);

        $token = PasswordResetToken::factory()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', 'valid-reset-token'),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'valid-reset-token',
            'password' => 'NewPass456',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password reset successfully');

        $this->assertDatabaseHas('password_reset_tokens', [
            'id' => $token->id,
        ]);
    }

    public function testResetPasswordInvalidToken(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'nonexistent-token',
            'password' => 'NewPass456',
        ]);

        $response->assertStatus(400);
    }

    public function testResetPasswordExpiredToken(): void
    {
        $user = User::factory()->create();

        PasswordResetToken::factory()->expired()->create([
            'user_id' => $user->id,
            'token' => hash('sha256', 'expired-token'),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'expired-token',
            'password' => 'NewPass456',
        ]);

        $response->assertStatus(400);
    }
}
