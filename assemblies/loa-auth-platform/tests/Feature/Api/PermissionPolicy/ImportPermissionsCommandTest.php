<?php

namespace Tests\Feature\Api\PermissionPolicy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Tests\Traits\WithJwt;
use Tests\Traits\WithJwtClaims;

class ImportPermissionsCommandTest extends TestCase
{
    use RefreshDatabase, WithJwt, WithJwtClaims;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = app_path('Permissions');
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $dirs = glob($this->tempDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    unlink($file);
                }
                rmdir($dir);
            }
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testImportFailsWhenFileNotFound(): void
    {
        $admin = $this->createAndLoginAdmin();

        $this->artisan('permissions:import', ['app' => 'nonexistent'])
            ->assertExitCode(1);
    }

    public function testImportFailsWhenJsonInvalid(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/invalid');
        File::put($this->tempDir . '/invalid/permissions.json', 'not valid json');

        $response = $this->artisan('permissions:import', ['app' => 'invalid']);

        $response->assertExitCode(1);
    }

    public function testImportFailsWhenMissingRequiredKeys(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/bad');
        File::put($this->tempDir . '/bad/permissions.json', json_encode(['routes' => []]));

        $response = $this->artisan('permissions:import', ['app' => 'bad']);

        $response->assertExitCode(1);
    }

    public function testImportFailsWhenAppMismatch(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/mismatch');
        File::put($this->tempDir . '/mismatch/permissions.json', json_encode([
            'app' => 'other-app',
            'version' => '1.0',
            'routes' => [],
        ]));

        $response = $this->artisan('permissions:import', ['app' => 'mismatch']);

        $response->assertExitCode(1);
    }

    public function testImportDryRunDoesNotSave(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/certificate');
        File::put($this->tempDir . '/certificate/permissions.json', json_encode([
            'app' => 'certificate',
            'version' => '1.0',
            'routes' => [
                [
                    'method' => 'GET',
                    'path' => 'api/v1/certificates',
                    'claims' => [
                        ['key' => 'certificate.read', 'precedence' => 1, 'filter' => 'all'],
                    ],
                ],
            ],
        ]));

        $response = $this->artisan('permissions:import', ['app' => 'certificate', '--dry-run' => true]);

        $response->assertExitCode(0);
        $this->assertDatabaseCount('route_policies', 0);
    }

    public function testImportCreatesClaimsAndPolicies(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/certificate');
        File::put($this->tempDir . '/certificate/permissions.json', json_encode([
            'app' => 'certificate',
            'version' => '1.0',
            'routes' => [
                [
                    'method' => 'GET',
                    'path' => 'api/v1/certificates',
                    'claims' => [
                        ['key' => 'certificate.read', 'precedence' => 1, 'filter' => 'all'],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => 'api/v1/certificates',
                    'claims' => [
                        ['key' => 'certificate.write', 'precedence' => 1, 'filter' => 'all'],
                    ],
                ],
            ],
        ]));

        $response = $this->artisan('permissions:import', ['app' => 'certificate']);

        $response->assertExitCode(0);
        $this->assertDatabaseCount('claims', 2);
        $this->assertDatabaseCount('route_policies', 2);
        $this->assertDatabaseHas('claims', ['key' => 'certificate.read']);
        $this->assertDatabaseHas('claims', ['key' => 'certificate.write']);
        $this->assertDatabaseHas('route_policies', [
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
        ]);
        $this->assertDatabaseHas('route_policies', [
            'app' => 'certificate',
            'method' => 'POST',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.write',
        ]);
    }

    public function testImportSkipsRoutesWithMissingFields(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/certificate');
        File::put($this->tempDir . '/certificate/permissions.json', json_encode([
            'app' => 'certificate',
            'version' => '1.0',
            'routes' => [
                [
                    'method' => 'GET',
                    'path' => 'api/v1/certificates',
                    'claims' => [
                        ['key' => 'certificate.read', 'precedence' => 1, 'filter' => 'all'],
                    ],
                ],
                [
                    'method' => 'DELETE',
                    'claims' => [
                        ['key' => 'certificate.write'],
                    ],
                ],
            ],
        ]));

        $response = $this->artisan('permissions:import', ['app' => 'certificate']);

        $response->assertExitCode(0);
        $this->assertDatabaseCount('route_policies', 1);
    }

    public function testImportUpdatesExistingPolicy(): void
    {
        $admin = $this->createAndLoginAdmin();
        $this->createClaim('certificate.read');
        \App\Models\RoutePolicy::create([
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'none',
        ]);

        File::ensureDirectoryExists($this->tempDir . '/certificate');
        File::put($this->tempDir . '/certificate/permissions.json', json_encode([
            'app' => 'certificate',
            'version' => '1.0',
            'routes' => [
                [
                    'method' => 'GET',
                    'path' => 'api/v1/certificates',
                    'claims' => [
                        ['key' => 'certificate.read', 'precedence' => 1, 'filter' => 'all'],
                    ],
                ],
            ],
        ]));

        $response = $this->artisan('permissions:import', ['app' => 'certificate']);

        $response->assertExitCode(0);
        $this->assertDatabaseHas('route_policies', [
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ]);
    }
}