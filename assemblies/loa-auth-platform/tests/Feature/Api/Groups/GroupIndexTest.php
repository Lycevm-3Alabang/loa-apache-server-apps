<?php

namespace Tests\Feature\Api\Groups;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class GroupIndexTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testIndexReturnsGroups(): void
    {
        UserGroup::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/groups', $this->jwtHeaders($this->admin));

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'description', 'tenant_id', 'members_count']]]);
    }

    public function testIndexFilterByNullTenant(): void
    {
        UserGroup::factory()->create(['tenant_id' => null, 'name' => 'Global']);

        $response = $this->getJson('/api/v1/groups?tenant_id=null', $this->jwtHeaders($this->admin));

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Global', $names);
    }

    public function testIndexRequiresPermission(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/groups', $this->jwtHeaders($user));

        $response->assertStatus(403);
    }
}
