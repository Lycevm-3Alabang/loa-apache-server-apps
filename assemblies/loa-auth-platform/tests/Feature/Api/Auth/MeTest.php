<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class MeTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testMeSuccess(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'me@loa.edu.ph',
        ]);

        $response = $this->getJson('/api/v1/auth/me', $this->jwtHeaders($user));

        $response->assertOk()
            ->assertJsonPath('email', 'me@loa.edu.ph')
            ->assertJsonPath('name', 'Test User')
            ->assertJsonStructure([
                'id', 'email', 'name', 'status', 'groups', 'permissions', 'created_at',
            ]);
    }

    public function testMeNoToken(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
