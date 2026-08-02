<?php

namespace Tests\Feature\Api\Groups;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class GroupSyncPermissionsTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testSyncPermissionsGrant(): void
    {
        $group = UserGroup::factory()->create();
        $perm = Permission::factory()->create();

        $response = $this->postJson("/api/v1/groups/{$group->id}/permissions", [
            'permissions' => [
                ['permission_key' => $perm->key, 'granted' => true],
            ],
        ], $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_group_permission', [
            'user_group_id' => $group->id,
            'permission_id' => $perm->id,
            'granted' => true,
        ]);
    }

    public function testSyncPermissionsRevoke(): void
    {
        $group = UserGroup::factory()->create();
        $perm = Permission::factory()->create();

        $group->permissions()->attach($perm->id, ['granted' => true, 'tenant_id' => null]);

        $response = $this->postJson("/api/v1/groups/{$group->id}/permissions", [
            'permissions' => [
                ['permission_key' => $perm->key, 'granted' => false],
            ],
        ], $this->jwtHeaders($this->admin));

        $response->assertOk();

        $this->assertDatabaseHas('user_group_permission', [
            'user_group_id' => $group->id,
            'permission_id' => $perm->id,
            'granted' => false,
        ]);
    }

    public function testSyncPermissionsInvalidKey(): void
    {
        $group = UserGroup::factory()->create();

        $response = $this->postJson("/api/v1/groups/{$group->id}/permissions", [
            'permissions' => [
                ['permission_key' => 'nonexistent.permission', 'granted' => true],
            ],
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('permissions.0.permission_key');
    }
}
