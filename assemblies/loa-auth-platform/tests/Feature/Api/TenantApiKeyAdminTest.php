<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class TenantApiKeyAdminTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAndLoginAdmin();

        $this->tenant = Tenant::create([
            'slug' => 'test-tenant',
            'name' => 'Test Tenant',
            'status' => 'active',
        ]);
    }

    private function headers(): array
    {
        return $this->jwtHeaders($this->admin);
    }

    public function testListKeys(): void
    {
        TenantApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Key 1',
            'key_hash' => hash('sha256', 'tk_1'),
            'secret_hash' => hash('sha256', 'tsk_1'),
        ]);

        $response = $this->getJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys",
            $this->headers(),
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Key 1');
    }

    public function testCreateKey(): void
    {
        $response = $this->postJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys",
            ['name' => 'New Key'],
            $this->headers(),
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'name', 'key', 'secret', 'tenant_id', 'created_at',
            ])
            ->assertJsonFragment(['name' => 'New Key']);

        $this->assertDatabaseHas('tenant_api_keys', [
            'tenant_id' => $this->tenant->id,
            'name' => 'New Key',
        ]);
    }

    public function testCreateKeyMaxLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            TenantApiKey::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Key {$i}",
                'key_hash' => hash('sha256', "tk_{$i}"),
                'secret_hash' => hash('sha256', "tsk_{$i}"),
            ]);
        }

        $response = $this->postJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys",
            ['name' => 'Fourth Key'],
            $this->headers(),
        );

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Maximum of 3 active API keys per tenant reached. Revoke an existing key first.',
            ]);
    }

    public function testRevokeKey(): void
    {
        $apiKey = TenantApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Revoke Me',
            'key_hash' => hash('sha256', 'tk_revoke'),
            'secret_hash' => hash('sha256', 'tsk_revoke'),
        ]);

        $response = $this->deleteJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$apiKey->id}",
            [],
            $this->headers(),
        );

        $response->assertOk()
            ->assertJsonFragment(['message' => 'API key revoked']);

        $this->assertDatabaseHas('tenant_api_keys', [
            'id' => $apiKey->id,
        ]);

        $this->assertNotNull($apiKey->fresh()->revoked_at);
    }

    public function testRevokeAlreadyRevoked(): void
    {
        $apiKey = TenantApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Already Revoked',
            'key_hash' => hash('sha256', 'tk_revoked'),
            'secret_hash' => hash('sha256', 'tsk_revoked'),
            'revoked_at' => now(),
        ]);

        $response = $this->deleteJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$apiKey->id}",
            [],
            $this->headers(),
        );

        $response->assertStatus(409)
            ->assertJson(['message' => 'API key is already revoked']);
    }

    public function testRevokeNonexistentKey(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->deleteJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys/{$fakeId}",
            [],
            $this->headers(),
        );

        $response->assertStatus(404);
    }

    public function testNonAdminCannotListKeys(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(
            "/api/v1/admin/tenants/{$this->tenant->id}/api-keys",
            $this->jwtHeaders($user),
        );

        $response->assertStatus(403);
    }
}
