<?php

namespace Tests\Feature\Api\UserGroups;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserPermissionRevokeTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testRevokeSuccess(): void
    {
        $user = User::factory()->create();
        $perm = Permission::factory()->create();

        $user->userPermissions()->attach($perm->id, ['granted' => true, 'tenant_id' => null]);

        $response = $this->deleteJson("/api/v1/users/{$user->id}/permissions/{$perm->key}", [], $this->jwtHeaders($this->admin));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('user_permission', [
            'user_id' => $user->id,
            'permission_id' => $perm->id,
        ]);
    }

    public function testRevokeNotFound(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/v1/users/{$user->id}/permissions/nonexistent.perm", [], $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }
}
