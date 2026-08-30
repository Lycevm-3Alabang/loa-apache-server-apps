<?php

namespace Tests\Feature\Web;

use App\Mail\SetPasswordMail;
use App\Models\PasswordSetToken;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantCreateUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'status' => 'active',
        ]);

        $adminGroup = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators']
        );
        $this->admin->userGroups()->attach($adminGroup);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->tenant->users()->attach($this->admin);

        $this->group = UserGroup::create([
            'name' => 'cert-user',
            'description' => 'Test group',
            'priority' => 10,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_post_creates_user_with_pending_status(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $this->group->id,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@test.com',
            'name' => 'New User',
            'status' => 'pending',
        ]);
    }

    public function test_post_creates_user_tenant_pivot(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $this->group->id,
            ]);

        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($this->tenant->users()->where('users.id', $user->id)->exists());
    }

    public function test_post_assigns_user_to_group(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $this->group->id,
            ]);

        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->userGroups()->where('user_groups.id', $this->group->id)->exists());
    }

    public function test_post_creates_password_set_token(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $this->group->id,
            ]);

        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('password_set_tokens', [
            'user_id' => $user->id,
        ]);
        $token = PasswordSetToken::where('user_id', $user->id)->first();
        $this->assertNotNull($token);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_post_queues_set_password_email(): void
    {
        Mail::fake();

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $this->group->id,
            ]);

        Mail::assertQueued(SetPasswordMail::class, function (SetPasswordMail $mail) {
            return $mail->hasTo('newuser@test.com');
        });
    }

    public function test_post_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'Duplicate User',
                'email' => 'existing@test.com',
                'group_id' => $this->group->id,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_post_rejects_missing_group_id(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
            ]);

        $response->assertSessionHasErrors('group_id');
    }

    public function test_post_rejects_group_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $otherGroup = UserGroup::create([
            'name' => 'other-group',
            'description' => 'Other tenant group',
            'priority' => 10,
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $otherGroup->id,
            ]);

        $response->assertSessionHasErrors('group_id');
    }

    public function test_show_page_displays_pending_users(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'Invited User',
                'email' => 'invited@test.com',
                'group_id' => $this->group->id,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.show', $this->tenant));

        $response->assertOk();
        $response->assertSee('Invited — awaiting password setup');
        $response->assertSee('invited@test.com');
        $response->assertSee('Invited User');
    }

    public function test_post_redirects_with_success_flash(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.users.store', $this->tenant), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'group_id' => $this->group->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'User created and set-password email sent.');
    }
}
