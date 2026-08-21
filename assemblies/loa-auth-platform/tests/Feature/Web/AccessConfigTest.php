<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\TenantAppEndpoint;
use App\Models\TenantEndpointGrant;
use App\Models\TenantEndpointOverride;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AccessConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->create();
        $group = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators']
        );
        $this->admin->userGroups()->attach($group->id);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
    }

    private function createEndpoint(string $method = 'GET', string $path = '/api/v1/appointments', ?string $tenantId = null): TenantAppEndpoint
    {
        return TenantAppEndpoint::factory()->create([
            'method' => $method,
            'path' => $path,
            'tenant_id' => $tenantId,
            'required_level' => 'read',
        ]);
    }

    // ===== Template =====

    public function testTemplateDownload(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.access-config.template', $this->tenant));

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=access-config-template.json');

        $data = json_decode($response->streamedContent(), true);
        $this->assertEquals('1.0', $data['version']);
        $this->assertArrayHasKey('groups', $data);
        $this->assertArrayHasKey('user_overrides', $data);
        $this->assertCount(3, $data['groups']);
    }

    public function testTemplateRequiresAdmin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get(route('admin.tenants.access-config.template', $this->tenant));

        $response->assertStatus(403);
    }

    // ===== Export =====

    public function testExportEmpty(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.access-config.export', $this->tenant));

        $response->assertOk();

        $data = json_decode($response->streamedContent(), true);
        $this->assertEquals('1.0', $data['version']);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertEquals($this->tenant->slug, $data['tenant_slug']);
        $this->assertEmpty($data['user_overrides']);
    }

    public function testExportWithData(): void
    {
        $group = UserGroup::factory()->create([
            'name' => 'Faculty',
            'tenant_id' => $this->tenant->id,
            'priority' => 5,
        ]);

        TenantEndpointGrant::factory()->create([
            'group_id' => $group->id,
            'tenant_id' => $this->tenant->id,
            'method' => 'GET',
            'path' => '/api/v1/appointments',
            'level' => 'read',
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.access-config.export', $this->tenant));

        $response->assertOk();

        $data = json_decode($response->streamedContent(), true);

        $facultyGroup = collect($data['groups'])->firstWhere('name', 'Faculty');
        $this->assertNotNull($facultyGroup);
        $this->assertEquals(5, $facultyGroup['priority']);
        $this->assertNotEmpty($facultyGroup['grants']);
        $this->assertEquals('GET', $facultyGroup['grants'][0]['method']);
    }

    public function testExportIncludesPlatformWideGroups(): void
    {
        $platformGroup = UserGroup::factory()->create([
            'name' => 'Platform-Admins',
            'tenant_id' => null,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.access-config.export', $this->tenant));

        $response->assertOk();

        $data = json_decode($response->streamedContent(), true);
        $groupNames = collect($data['groups'])->pluck('name')->toArray();
        $this->assertContains('Platform-Admins', $groupNames);

        $platformGroupData = collect($data['groups'])->firstWhere('name', 'Platform-Admins');
        $this->assertNull($platformGroupData['tenant_id']);
    }

    public function testExportTenantNotFound(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get('/admin/tenants/nonexistent/access-config/export');

        $response->assertStatus(404);
    }

    public function testExportSuspendedTenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.access-config.export', $tenant));

        $response->assertStatus(403);
    }

    // ===== Import (Preview) =====

    public function testImportPreviewCreatesGroups(): void
    {
        $this->createEndpoint('GET', '/api/v1/appointments', $this->tenant->id);

        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'description' => 'Teaching staff',
                    'priority' => 5,
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'preview',
                'groups' => ['create' => ['Faculty']],
                'grants' => ['upsert' => 1],
            ]);

        // Verify nothing was actually created
        $this->assertDatabaseMissing('user_groups', ['name' => 'Faculty', 'tenant_id' => $this->tenant->id]);
    }

    public function testImportPreviewUpdatesExistingGroups(): void
    {
        UserGroup::factory()->create([
            'name' => 'Faculty',
            'tenant_id' => $this->tenant->id,
            'description' => 'Old description',
            'priority' => 10,
        ]);

        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'description' => 'New description',
                    'priority' => 5,
                    'grants' => [],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'preview',
                'groups' => ['update' => ['Faculty']],
            ]);
    }

    public function testImportPreviewReportsMissingEndpoints(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/unknown', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'preview',
            ]);

        $data = $response->json();
        $this->assertFalse($data['endpoint_validation']['valid']);
        $this->assertContains('GET /api/v1/unknown', $data['endpoint_validation']['missing_endpoints']);
    }

    public function testImportPreviewReportsUnknownUserEmail(): void
    {
        $payload = [
            'version' => '1.0',
            'user_overrides' => [
                [
                    'email' => 'unknown@example.com',
                    'overrides' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'preview',
            ]);

        $data = $response->json();
        $this->assertContains('User not found: unknown@example.com', $data['user_overrides']['errors']);
    }

    public function testImportValidationFailsOnInvalidJson(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), [
                'version' => '2.0',
            ]);

        $response->assertStatus(422);
    }

    public function testImportValidationFailsOnMissingGrantMethod(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'grants' => [
                        ['path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertStatus(422);
    }

    public function testImportValidationFailsOnInvalidLevel(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'superadmin'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertStatus(422);
    }

    // ===== Import (Apply) =====

    public function testImportApplyCreatesGroupsAndGrants(): void
    {
        $this->createEndpoint('GET', '/api/v1/appointments', $this->tenant->id);

        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'description' => 'Teaching staff',
                    'priority' => 5,
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
                [
                    'name' => 'Students',
                    'priority' => 20,
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'applied',
                'groups' => ['created' => 2, 'updated' => 0],
            ]);

        $this->assertDatabaseHas('user_groups', ['name' => 'Faculty', 'tenant_id' => $this->tenant->id, 'priority' => 5]);
        $this->assertDatabaseHas('user_groups', ['name' => 'Students', 'tenant_id' => $this->tenant->id, 'priority' => 20]);
    }

    public function testImportApplyUpdatesExistingGroups(): void
    {
        UserGroup::factory()->create([
            'name' => 'Faculty',
            'tenant_id' => $this->tenant->id,
            'description' => 'Old description',
            'priority' => 10,
        ]);

        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'description' => 'New description',
                    'priority' => 5,
                    'grants' => [],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'applied',
                'groups' => ['created' => 0, 'updated' => 1],
            ]);

        $this->assertDatabaseHas('user_groups', [
            'name' => 'Faculty',
            'tenant_id' => $this->tenant->id,
            'description' => 'New description',
            'priority' => 5,
        ]);
    }

    public function testImportApplyNoneLevelDeletesGrant(): void
    {
        $group = UserGroup::factory()->create([
            'name' => 'Faculty',
            'tenant_id' => $this->tenant->id,
        ]);

        TenantEndpointGrant::factory()->create([
            'group_id' => $group->id,
            'tenant_id' => $this->tenant->id,
            'method' => 'GET',
            'path' => '/api/v1/appointments',
            'level' => 'read',
        ]);

        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'deny'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'applied',
            ]);

        $this->assertDatabaseMissing('tenant_endpoint_grants', [
            'group_id' => $group->id,
            'tenant_id' => $this->tenant->id,
            'method' => 'GET',
            'path' => '/api/v1/appointments',
        ]);
    }

    public function testImportApplyUserOverrides(): void
    {
        $this->createEndpoint('GET', '/api/v1/appointments', $this->tenant->id);

        $payload = [
            'version' => '1.0',
            'user_overrides' => [
                [
                    'email' => $this->admin->email,
                    'overrides' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'applied',
                'user_overrides' => ['upserted' => 1],
            ]);

        $this->assertDatabaseHas('tenant_endpoint_overrides', [
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'method' => 'GET',
            'path' => '/api/v1/appointments',
            'level' => 'read',
        ]);
    }

    public function testImportApplyNoneLevelDeletesOverride(): void
    {
        TenantEndpointOverride::factory()->create([
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'method' => 'GET',
            'path' => '/api/v1/appointments',
            'level' => 'read',
        ]);

        $payload = [
            'version' => '1.0',
            'user_overrides' => [
                [
                    'email' => $this->admin->email,
                    'overrides' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'deny'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', $payload);

        $response->assertOk();

        $this->assertDatabaseMissing('tenant_endpoint_overrides', [
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'method' => 'GET',
            'path' => '/api/v1/appointments',
        ]);
    }

    // ===== Import with file upload =====

    public function testImportWithFileUpload(): void
    {
        $this->createEndpoint('GET', '/api/v1/appointments', $this->tenant->id);

        $payload = [
            'version' => '1.0',
            'groups' => [
                [
                    'name' => 'Faculty',
                    'priority' => 5,
                    'grants' => [
                        ['method' => 'GET', 'path' => '/api/v1/appointments', 'level' => 'read'],
                    ],
                ],
            ],
        ];

        $file = UploadedFile::fake()->createWithContent('access-config.json', json_encode($payload));

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'applied',
                'groups' => ['created' => 1],
            ]);

        $this->assertDatabaseHas('user_groups', ['name' => 'Faculty', 'tenant_id' => $this->tenant->id]);
    }

    // ===== Tenant checks =====

    public function testImportSuspendedTenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $payload = [
            'version' => '1.0',
            'groups' => [],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $tenant), $payload);

        $response->assertStatus(403);
    }

    public function testImportTenantNotFound(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/admin/tenants/nonexistent/access-config/import', $payload);

        $response->assertStatus(404);
    }

    public function testImportFormRenders(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.tenants.access-config.import', $this->tenant));

        $response->assertOk();
    }

    // ===== Dry run vs confirm =====

    public function testDefaultIsPreviewNoApply(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [
                ['name' => 'Faculty', 'priority' => 5, 'grants' => []],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertOk()
            ->assertJson(['status' => 'preview']);

        $this->assertDatabaseMissing('user_groups', ['name' => 'Faculty', 'tenant_id' => $this->tenant->id]);
    }

    public function testDryRunOverridesConfirm(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [
                ['name' => 'Faculty', 'priority' => 5, 'grants' => []],
            ],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?dry_run=1&confirm=1', $payload);

        $response->assertOk()
            ->assertJson(['status' => 'preview']);

        $this->assertDatabaseMissing('user_groups', ['name' => 'Faculty', 'tenant_id' => $this->tenant->id]);
    }

    // ===== Empty import =====

    public function testEmptyImportIsValid(): void
    {
        $payload = [
            'version' => '1.0',
            'groups' => [],
            'user_overrides' => [],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant) . '?confirm=1', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'applied',
                'groups' => ['created' => 0, 'updated' => 0],
                'grants' => ['upserted' => 0],
                'user_overrides' => ['upserted' => 0],
            ]);
    }

    // ===== Non-admin access =====

    public function testNonAdminCannotAccessTemplate(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get(route('admin.tenants.access-config.template', $this->tenant));

        $response->assertStatus(403);
    }

    public function testNonAdminCannotAccessExport(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get(route('admin.tenants.access-config.export', $this->tenant));

        $response->assertStatus(403);
    }

    public function testNonAdminCannotImport(): void
    {
        $user = User::factory()->create();

        $payload = [
            'version' => '1.0',
            'groups' => [],
        ];

        $response = $this->actingAs($user, 'web')
            ->postJson(route('admin.tenants.access-config.import.store', $this->tenant), $payload);

        $response->assertStatus(403);
    }
}
