<?php

namespace Tests\Feature\Api\UserGroups;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserPermissionsIndexTest extends TestCase
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

        $response = $this->getJson("/api/v1/users/{$user->id}/permissions", $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonStructure(['user_id', 'permissions', 'groups', 'overrides']);
    }
}
