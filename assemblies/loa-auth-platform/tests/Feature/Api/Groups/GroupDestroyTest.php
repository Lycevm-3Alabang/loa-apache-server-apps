<?php

namespace Tests\Feature\Api\Groups;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class GroupDestroyTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testDestroySuccess(): void
    {
        $group = UserGroup::factory()->create();

        $response = $this->deleteJson("/api/v1/groups/{$group->id}", [], $this->jwtHeaders($this->admin));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);
    }

    public function testDestroyNotFound(): void
    {
        $response = $this->deleteJson('/api/v1/groups/99999', [], $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }

    public function testDestroyDetachesMembersAndPermissions(): void
    {
        $group = UserGroup::factory()->create();
        $user = User::factory()->create();

        $group->users()->attach($user->id);

        $response = $this->deleteJson("/api/v1/groups/{$group->id}", [], $this->jwtHeaders($this->admin));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('user_user_group', [
            'user_group_id' => $group->id,
        ]);
    }
}
