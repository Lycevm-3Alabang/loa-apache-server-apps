<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class LogoutTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testLogoutSuccess(): void
    {
        $user = User::factory()->create();
        $refreshToken = $this->createRefreshToken($user);

        $response = $this->postJson('/api/v1/auth/logout', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(204);

        $decoded = app(\App\Services\JWTService::class)->validate($refreshToken);
        $hashedJti = hash('sha256', $decoded['jti'] ?? '');

        $this->assertDatabaseHas('refresh_tokens', [
            'jti' => $hashedJti,
        ]);

        $record = RefreshToken::where('jti', $hashedJti)->first();
        $this->assertNotNull($record->revoked_at);
    }

    public function testLogoutMissingToken(): void
    {
        $response = $this->postJson('/api/v1/auth/logout', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('refresh_token');
    }
}
