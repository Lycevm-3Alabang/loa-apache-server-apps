<?php

namespace Tests\Feature\Api\UserGroups;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserGroupsIndexTest extends TestCase
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
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();

        $user->userGroups()->attach($group->id);

        $response = $this->getJson("/api/v1/users/{$user->id}/groups", $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonCount(1, 'groups');
    }

    public function testIndexUserNotFound(): void
    {
        $response = $this->getJson('/api/v1/users/nonexistent/groups', $this->jwtHeaders($this->admin));

        $response->assertStatus(404);
    }
}
