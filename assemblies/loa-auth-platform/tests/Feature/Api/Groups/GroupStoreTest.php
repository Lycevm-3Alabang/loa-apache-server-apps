<?php

namespace Tests\Feature\Api\Groups;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class GroupStoreTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testStoreSuccess(): void
    {
        $response = $this->postJson('/api/v1/groups', [
            'name' => 'Faculty',
            'description' => 'Teaching staff',
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Faculty');

        $this->assertDatabaseHas('user_groups', [
            'name' => 'Faculty',
            'description' => 'Teaching staff',
        ]);
    }

    public function testStoreDuplicateName(): void
    {
        UserGroup::factory()->create(['name' => 'Faculty', 'tenant_id' => null]);

        $response = $this->postJson('/api/v1/groups', [
            'name' => 'Faculty',
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(409);
    }

    public function testStoreInvalidData(): void
    {
        $response = $this->postJson('/api/v1/groups', [
            'name' => '',
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
