<?php

namespace Tests\Feature\Api\PermissionPolicy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

        $exitCode = Artisan::call('permissions:import', ['app' => 'nonexistent']);

        $this->assertEquals(1, $exitCode);
    }

    public function testImportFailsWhenJsonInvalid(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/invalid');
        File::put($this->tempDir . '/invalid/permissions.json', 'not valid json');

        $exitCode = Artisan::call('permissions:import', ['app' => 'invalid']);

        $this->assertEquals(1, $exitCode);
    }

    public function testImportFailsWhenMissingRequiredKeys(): void
    {
        $admin = $this->createAndLoginAdmin();
        File::ensureDirectoryExists($this->tempDir . '/bad');
        File::put($this->tempDir . '/bad/permissions.json', json_encode(['routes' => []]));

        $exitCode = Artisan::call('permissions:import', ['app' => 'bad']);

        $this->assertEquals(1, $exitCode);
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

        $exitCode = Artisan::call('permissions:import', ['app' => 'mismatch']);

        $this->assertEquals(1, $exitCode);
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

        $exitCode = Artisan::call('permissions:import', ['app' => 'certificate', '--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
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

        $exitCode = Artisan::call('permissions:import', ['app' => 'certificate']);

        $this->assertEquals(0, $exitCode);
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

        $exitCode = Artisan::call('permissions:import', ['app' => 'certificate']);

        $this->assertEquals(0, $exitCode);
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

        $exitCode = Artisan::call('permissions:import', ['app' => 'certificate']);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('route_policies', [
            'app' => 'certificate',
            'method' => 'GET',
            'path' => 'api/v1/certificates',
            'claim_key' => 'certificate.read',
            'filter' => 'all',
        ]);
    }
}
