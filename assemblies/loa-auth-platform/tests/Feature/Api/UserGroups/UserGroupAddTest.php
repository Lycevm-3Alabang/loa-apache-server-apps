<?php

namespace Tests\Feature\Api\UserGroups;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserGroupAddTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testAddSuccess(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $response = $this->postJson("/api/v1/users/{$user->id}/groups", [
            'group_id' => $group->id,
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $group->id,
        ]);
    }

    public function testAddDuplicate(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $user->userGroups()->attach($group->id);

        $response = $this->postJson("/api/v1/users/{$user->id}/groups", [
            'group_id' => $group->id,
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(409);
    }

    public function testAddUserNotFound(): void
    {
        $group = UserGroup::factory()->create();

        $response = $this->postJson('/api/v1/users/nonexistent/groups', [
            'group_id' => $group->id,
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }
}
