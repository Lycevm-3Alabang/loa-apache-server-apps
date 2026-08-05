<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class RegisterTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testRegisterSuccess(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'new@lyceumalabang.edu.ph',
            'password' => 'Test1234!',
            'name' => 'New User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'email', 'name', 'status', 'created_at',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@lyceumalabang.edu.ph',
            'name' => 'New User',
            'status' => 'active',
        ]);
    }

    public function testRegisterDuplicateEmail(): void
    {
        User::factory()->create(['email' => 'existing@lyceumalabang.edu.ph']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'existing@lyceumalabang.edu.ph',
            'password' => 'Test1234!',
            'name' => 'Duplicate',
        ]);

        $response->assertStatus(409);
    }

    public function testRegisterInvalidEmail(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'Test1234!',
            'name' => 'User',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function testRegisterShortPassword(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'user@lyceumalabang.edu.ph',
            'password' => 'short',
            'name' => 'User',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function testRegisterPasswordNoUppercase(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'user@lyceumalabang.edu.ph',
            'password' => 'test1234!',
            'name' => 'User',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function testRegisterMissingName(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'user@lyceumalabang.edu.ph',
            'password' => 'Test1234!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
