<?php

namespace Tests\Feature\Api\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserShowTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testShowSuccess(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/v1/users/{$user->id}", $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonStructure(['id', 'email', 'name', 'status', 'groups', 'permissions']);
    }

    public function testShowNotFound(): void
    {
        $response = $this->getJson('/api/v1/users/nonexistent-id', $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }
}
