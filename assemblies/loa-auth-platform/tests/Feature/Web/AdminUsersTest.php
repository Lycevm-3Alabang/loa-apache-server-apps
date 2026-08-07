<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // Create admin user with users.manage permission
        $this->admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'status' => 'active'
        ]);
        
        // Add to admin group that has permissions
        $adminGroup = UserGroup::where('name', 'admin')->first();
        if ($adminGroup) {
            $this->admin->userGroups()->attach($adminGroup);
        }
    }

    public function testAdminCanCreateUserWithActivation(): void
    {
        $response = $this->post('/admin/users', [
            'email' => 'newuser@lyceumalabang.edu.ph',
            'name' => 'New User',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('status', 'User created. Activation email sent.');
            
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@lyceumalabang.edu.ph',
            'name' => 'New User',
            'status' => 'pending'
        ]);
        
        // Check that an activation record was created
        $this->assertDatabaseHas('activations', [
            'user_id' => User::where('email', 'newuser@lyceumalabang.edu.ph')->first()->id
        ]);
    }
    
    public function testAdminCanResendActivation(): void
    {
        // Create a pending user
        $pendingUser = User::factory()->create([
            'email' => 'pendinguser@lyceumalabang.edu.ph',
            'name' => 'Pending User',
            'status' => 'pending'
        ]);
        
        // Create an activation for this user
        $activationService = app(\App\Services\ActivationService::class);
        $rawToken = $activationService->createActivation($pendingUser);

        $response = $this->post("/admin/users/{$pendingUser->id}/resend-activation");

        $response->assertRedirect()
            ->assertSessionHas('status', 'Activation email resent successfully.');
    }
}