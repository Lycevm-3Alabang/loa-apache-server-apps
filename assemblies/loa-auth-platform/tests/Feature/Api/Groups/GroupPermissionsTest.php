<?php

namespace Tests\Feature\Api\Groups;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class GroupPermissionsTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testShowPermissionsSuccess(): void
    {
        $group = UserGroup::factory()->create();
        $perm = Permission::factory()->create();

        $group->permissions()->attach($perm->id, ['granted' => true, 'tenant_id' => null]);

        $response = $this->getJson("/api/v1/groups/{$group->id}/permissions", $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonPath('group_id', $group->id)
            ->assertJsonCount(1, 'permissions');
    }

    public function testShowPermissionsNotFound(): void
    {
        $response = $this->getJson('/api/v1/groups/99999/permissions', $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }
}
