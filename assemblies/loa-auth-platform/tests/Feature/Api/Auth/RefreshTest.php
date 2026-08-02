<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class RefreshTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testRefreshSuccess(): void
    {
        $user = User::factory()->create();
        $refreshToken = $this->createRefreshToken($user);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token', 'refresh_token', 'token_type', 'expires_in',
            ]);
    }

    public function testRefreshInvalidToken(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid-token-string',
        ]);

        $response->assertStatus(401);
    }

    public function testRefreshRevokedToken(): void
    {
        $user = User::factory()->create();
        $jwt = app(\App\Services\JWTService::class);

        $authorization = app(\App\Services\AuthorizationService::class);
        $groups = $authorization->getGroups($user->id);
        $permissions = $authorization->getPermissions($user->id);

        $refreshJwt = $jwt->generateRefreshToken([
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'groups' => $groups,
            'permissions' => $permissions,
        ]);

        $claims = $jwt->validate($refreshJwt);

        RefreshToken::create([
            'user_id' => $user->id,
            'jti' => hash('sha256', $claims['jti'] ?? ''),
            'expires_at' => now()->addDays(7),
            'revoked_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshJwt,
        ]);

        $response->assertStatus(401);
    }

    public function testRefreshMissingToken(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('refresh_token');
    }
}
