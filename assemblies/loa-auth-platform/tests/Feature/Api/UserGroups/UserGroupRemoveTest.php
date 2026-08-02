<?php

namespace Tests\Feature\Api\UserGroups;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserGroupRemoveTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testRemoveSuccess(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $user->userGroups()->attach($group->id);

        $response = $this->deleteJson("/api/v1/users/{$user->id}/groups/{$group->id}", [], $this->jwtHeaders($this->admin));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $group->id,
        ]);
    }

    public function testRemoveUserNotFound(): void
    {
        $group = UserGroup::factory()->create();

        $response = $this->deleteJson("/api/v1/users/nonexistent/groups/{$group->id}", [], $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }
}
