<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function testRegisterEndpointRemoved(): void
    {
        // The register endpoint should no longer exist
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'new@lyceumalabang.edu.ph',
            'password' => 'Test1234!',
            'name' => 'New User',
        ]);

        $response->assertStatus(404);
    }
}