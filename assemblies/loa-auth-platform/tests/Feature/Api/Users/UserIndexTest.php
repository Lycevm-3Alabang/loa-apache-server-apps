<?php

namespace Tests\Feature\Api\Users;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserIndexTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testIndexSuccess(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users', $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'email', 'name', 'status']]]);
    }

    public function testIndexRequiresPermission(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/users', $this->jwtHeaders($user));

        $response->assertStatus(403);
    }

    public function testIndexNoToken(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(401);
    }
}
