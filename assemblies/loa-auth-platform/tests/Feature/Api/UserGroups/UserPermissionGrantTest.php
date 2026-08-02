<?php

namespace Tests\Feature\Api\UserGroups;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserPermissionGrantTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testGrantSuccess(): void
    {
        $user = User::factory()->create();
        $perm = Permission::factory()->create();

        $response = $this->postJson("/api/v1/users/{$user->id}/permissions", [
            'permission_key' => $perm->key,
            'granted' => true,
        ], $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_permission', [
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'granted' => true,
        ]);
    }

    public function testGrantInvalidKey(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/v1/users/{$user->id}/permissions", [
            'permission_key' => 'nonexistent.permission',
            'granted' => true,
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('permission_key');
    }
}
