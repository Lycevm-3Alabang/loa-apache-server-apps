<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class ActivationTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    public function testActivateUserSuccess(): void
    {
        // Create a pending user with activation token
        $user = User::factory()->create([
            'email' => 'pending@lyceumalabang.edu.ph',
            'name' => 'Pending User',
            'status' => 'pending',
        ]);
        
        $activationService = app(\App\Services\ActivationService::class);
        $rawToken = $activationService->createActivation($user);
        
        // Test that we get 200 when activating with valid token
        $response = $this->postJson('/api/v1/auth/activate', [
            'token' => $rawToken,
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);
        
        $response->assertStatus(200);
    }
    
    public function testActivateUserInvalidToken(): void
    {
        // Test that we get 400 when activating with invalid token
        $response = $this->postJson('/api/v1/auth/activate', [
            'token' => 'invalid-token',
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);
        
        $response->assertStatus(400);
    }
    
    public function testActivateUserExpiredToken(): void
    {
        // Create a user with expired activation token (directly in db)
        $user = User::factory()->create([
            'email' => 'expired@lyceumalabang.edu.ph',
            'name' => 'Expired User',
            'status' => 'pending',
        ]);
        
        \App\Models\Activation::create([
            'user_id' => $user->id,
            'token' => hash('sha256', 'valid-token'),
            'expires_at' => now()->subHours(25), // Expired
        ]);
        
        // Test that we get 400 when activating with expired token
        $response = $this->postJson('/api/v1/auth/activate', [
            'token' => 'valid-token',
            'password' => 'Test1234!',
            'password_confirmation' => 'Test1234!',
        ]);
        
        $response->assertStatus(400);
    }
    
    public function testGetLoginWithPendingAccount(): void
    {
        // Create a pending user
        $user = User::factory()->create([
            'email' => 'pending@lyceumalabang.edu.ph',
            'password' => bcrypt('Test1234'),
            'status' => 'pending',
        ]);
        
        // Try to login with the pending account - should fail with 403
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'pending@lyceumalabang.edu.ph',
            'password' => 'Test1234',
        ]);
        
        $response->assertStatus(403)
            ->assertJson(['message' => 'Account not activated. Please check your email.']);
    }
}