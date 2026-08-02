<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class VerifyTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testVerifyValidToken(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/auth/verify', $this->jwtHeaders($user));

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonStructure(['valid', 'sub', 'email', 'name', 'groups', 'permissions']);
    }

    public function testVerifyInvalidToken(): void
    {
        $response = $this->getJson('/api/v1/auth/verify', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('valid', false);
    }

    public function testVerifyNoToken(): void
    {
        $response = $this->getJson('/api/v1/auth/verify');

        $response->assertStatus(401);
    }
}
