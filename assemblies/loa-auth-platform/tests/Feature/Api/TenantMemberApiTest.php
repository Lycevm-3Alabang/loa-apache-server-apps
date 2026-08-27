<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class TenantMemberApiTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private Tenant $tenant;
    private TenantApiKey $apiKey;
    private string $rawKey;
    private string $rawSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'slug' => 'test-tenant',
            'name' => 'Test Tenant',
            'status' => 'active',
            'app_url' => 'https://test.example.com',
        ]);

        $pair = TenantApiKey::generateKeyPair();

        $this->apiKey = TenantApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Key',
            'key_hash' => $pair['key_hash'],
            'secret_hash' => $pair['secret_hash'],
        ]);

        $this->rawKey = $pair['key'];
        $this->rawSecret = $pair['secret'];
    }

    private function apiKeyHeaders(): array
    {
        return ['X-Api-Key' => "{$this->rawKey}:{$this->rawSecret}"];
    }

    public function testAuthMissingHeader(): void
    {
        $response = $this->getJson('/api/v1/tenant/members');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid API key credentials']);
    }

    public function testAuthInvalidFormat(): void
    {
        $response = $this->getJson('/api/v1/tenant/members', [
            'X-Api-Key' => 'no-colon-here',
        ]);

        $response->assertStatus(401);
    }

    public function testAuthInvalidKey(): void
    {
        $response = $this->getJson('/api/v1/tenant/members', [
            'X-Api-Key' => 'tk_invalid:tsk_invalid',
        ]);

        $response->assertStatus(401);
    }

    public function testAuthInvalidSecret(): void
    {
        $response = $this->getJson('/api/v1/tenant/members', [
            'X-Api-Key' => "{$this->rawKey}:tsk_wrong_secret",
        ]);

        $response->assertStatus(401);
    }

    public function testAuthRevokedKey(): void
    {
        $this->apiKey->update(['revoked_at' => now()]);

        $response = $this->getJson('/api/v1/tenant/members', $this->apiKeyHeaders());

        $response->assertStatus(401);
    }

    public function testAuthExpiredKey(): void
    {
        $this->apiKey->update(['expires_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/tenant/members', $this->apiKeyHeaders());

        $response->assertStatus(401);
    }

    public function testListMembersEmpty(): void
    {
        $response = $this->getJson('/api/v1/tenant/members', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJson([
                'data' => [],
                'has_more' => false,
            ]);
    }

    public function testListMembersScopedToTenant(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id);

        $otherTenant = Tenant::create([
            'slug' => 'other',
            'name' => 'Other',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create();
        $otherTenant->users()->attach($otherUser->id);

        $response = $this->getJson('/api/v1/tenant/members', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->id);
    }

    public function testListMembersFilterByStatus(): void
    {
        $active = User::factory()->create(['status' => 'active']);
        $pending = User::factory()->create(['status' => 'pending']);
        $this->tenant->users()->attach([$active->id, $pending->id]);

        $response = $this->getJson('/api/v1/tenant/members?status=pending', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);
    }

    public function testAddExistingUser(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/tenant/members', [
            'email' => $user->email,
        ], $this->apiKeyHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment(['email' => $user->email]);

        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function testAddNonexistentUser(): void
    {
        $response = $this->postJson('/api/v1/tenant/members', [
            'email' => 'nonexistent@example.com',
        ], $this->apiKeyHeaders());

        $response->assertStatus(404)
            ->assertJson(['message' => 'User not found']);
    }

    public function testAddAlreadyMember(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id);

        $response = $this->postJson('/api/v1/tenant/members', [
            'email' => $user->email,
        ], $this->apiKeyHeaders());

        $response->assertStatus(409)
            ->assertJson(['message' => 'User is already a member of this tenant']);
    }

    public function testRevokeMembership(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id);

        $response = $this->deleteJson("/api/v1/tenant/members/{$user->id}", [], $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonFragment(['email' => $user->email]);

        $this->assertDatabaseMissing('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function testRevokeNonMember(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/v1/tenant/members/{$user->id}", [], $this->apiKeyHeaders());

        $response->assertStatus(404)
            ->assertJson(['message' => 'User is not a member of this tenant']);
    }

    public function testRevokeAlsoRemovesGroups(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create([
            'name' => 'test-group',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->tenant->users()->attach($user->id);
        $user->userGroups()->attach($group->id);

        $response = $this->deleteJson("/api/v1/tenant/members/{$user->id}", [], $this->apiKeyHeaders());

        $response->assertOk();

        $this->assertDatabaseMissing('user_user_group', [
            'user_id' => $user->id,
            'user_group_id' => $group->id,
        ]);
    }

    public function testInviteNewUser(): void
    {
        $response = $this->postJson('/api/v1/tenant/members/invite', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ], $this->apiKeyHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
        $this->assertDatabaseHas('user_tenants', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function testInviteWithGroups(): void
    {
        $group = UserGroup::create([
            'name' => 'cert-admin',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/tenant/members/invite', [
            'name' => 'Grouped User',
            'email' => 'grouped@example.com',
            'groups' => ['cert-admin'],
        ], $this->apiKeyHeaders());

        $response->assertStatus(201);

        $user = \App\Models\User::where('email', 'grouped@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->userGroups->contains($group->id));
    }

    public function testInviteExistingEmail(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/tenant/members/invite', [
            'name' => 'Dupe',
            'email' => 'existing@example.com',
        ], $this->apiKeyHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function testInviteMissingName(): void
    {
        $response = $this->postJson('/api/v1/tenant/members/invite', [
            'email' => 'noname@example.com',
        ], $this->apiKeyHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function testCrossTenantKeyIsolation(): void
    {
        $otherTenant = Tenant::create([
            'slug' => 'other',
            'name' => 'Other',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $otherTenant->users()->attach($user->id);

        $response = $this->getJson('/api/v1/tenant/members', $this->apiKeyHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
