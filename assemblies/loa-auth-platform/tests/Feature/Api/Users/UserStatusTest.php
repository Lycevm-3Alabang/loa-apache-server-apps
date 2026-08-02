<?php

namespace Tests\Feature\Api\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class UserStatusTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();
    }

    public function testUpdateStatusSuccess(): void
    {
        $user = User::factory()->active()->create();

        $response = $this->patchJson("/api/v1/users/{$user->id}/status", [
            'status' => 'disabled',
        ], $this->jwtHeaders($this->admin));

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'disabled',
        ]);
    }

    public function testUpdateStatusInvalid(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/api/v1/users/{$user->id}/status", [
            'status' => 'invalid-status',
        ], $this->jwtHeaders($this->admin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
