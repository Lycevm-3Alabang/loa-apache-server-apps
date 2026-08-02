<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function testNonAdminRedirectsToLogin(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    public function testNonAdminGroupAborts403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/admin/users');

        $response->assertStatus(403);
    }

    public function testAdminCanAccess(): void
    {
        $admin = User::factory()->create();
        $group = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators']
        );
        $admin->userGroups()->attach($group->id);

        $response = $this->actingAs($admin, 'web')
            ->get('/admin/users');

        $response->assertOk();
    }
}
