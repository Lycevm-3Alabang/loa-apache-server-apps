<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BulkUserImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        Mail::fake();

        $this->admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'status' => 'active',
        ]);

        $adminGroup = UserGroup::firstOrCreate(
            ['name' => config('auth-web.admin_group')],
            ['description' => 'Platform administrators', 'priority' => 1]
        );
        $this->admin->userGroups()->attach($adminGroup);

        $permission = Permission::firstOrCreate(
            ['key' => 'users.manage'],
            ['description' => 'Manage users']
        );
        $adminGroup->permissions()->syncWithoutDetaching([
            $permission->id => ['granted' => true]
        ]);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
    }

    private function createGroup(string $name, Tenant $tenant, int $priority = 10): UserGroup
    {
        return UserGroup::create([
            'name' => $name,
            'description' => "Test group: {$name}",
            'priority' => $priority,
            'tenant_id' => $tenant->id,
        ]);
    }

    private function makeCsv(array $rows): UploadedFile
    {
        $content = "name,email,tenant_app,user_group\n";
        foreach ($rows as $row) {
            $content .= implode(',', $row) . "\n";
        }

        return UploadedFile::fake()->createWithContent('users.csv', $content);
    }

    // ===== Upload Form =====

    public function testImportFormRequiresAdmin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/admin/users/import');

        $response->assertStatus(403);
    }

    public function testAdminCanSeeImportForm(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/users/import');

        $response->assertOk();
    }

    // ===== CSV Upload & Preview =====

    public function testPreviewValidCsv(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertViewHas('rows');
        $response->assertViewHas('summary');

        $rows = $response->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertEquals('ready', $rows[0]['status']);
    }

    public function testPreviewRejectsInvalidHeaders(): void
    {
        $content = "name,email,invalid_column\nJohn,john@test.com,foo\n";
        $file = UploadedFile::fake()->createWithContent('users.csv', $content);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function testPreviewRejectsExtraColumns(): void
    {
        $content = "name,email,tenant_app,user_group,extra
John,john@test.com,loa,cert-admin,value
";
        $file = UploadedFile::fake()->createWithContent('users.csv', $content);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function testPreviewRejectsMissingFile(): void
    {
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview');

        $response->assertStatus(422);
    }

    public function testPreviewValidatesTenantDoesNotExist(): void
    {
        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'invalid-tenant', 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $rows = $response->viewData('rows');
        $this->assertEquals('error', $rows[0]['status']);
        $this->assertStringContainsString('tenant_app does not exist', $rows[0]['remarks']);
    }

    public function testPreviewValidatesGroupDoesNotExist(): void
    {
        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'invalid-group'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $rows = $response->viewData('rows');
        $this->assertEquals('error', $rows[0]['status']);
        $this->assertStringContainsString('user_group does not exist', $rows[0]['remarks']);
    }

    public function testPreviewDetectsDuplicateEmailInCsv(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'cert-admin'],
            ['John Doe2', 'john@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertEquals('ready', $rows[0]['status']);
        $this->assertEquals('error', $rows[1]['status']);
        $this->assertStringContainsString('Duplicate email found in uploaded file', $rows[1]['remarks']);
    }

    public function testPreviewRecognizesExistingUser(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $existingUser = User::factory()->create([
            'email' => 'existing@test.com',
            'name' => 'Existing User',
            'status' => 'active',
        ]);

        $file = $this->makeCsv([
            ['Existing User', 'existing@test.com', $this->tenant->slug, 'cert-admin'],
            ['New User', 'new@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertEquals('ready_existing', $rows[0]['status']);
        $this->assertStringContainsString('Existing user', $rows[0]['remarks']);
        $this->assertEquals('ready', $rows[1]['status']);
    }

    // ===== Process Import =====

    public function testProcessImportsNewUsers(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'cert-admin'],
            ['Jane Smith', 'jane@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        // Preview first
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);
        $response->assertOk();

        // Process
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/process', [
            'removed_rows' => '[]',
        ]);

        $response->assertOk();
        $response->assertViewHas('summary');

        $summary = $response->viewData('summary');
        $this->assertEquals(2, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john@test.com',
            'name' => 'John Doe',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'jane@test.com',
            'name' => 'Jane Smith',
            'status' => 'pending',
        ]);

        // Verify tenant membership
        $john = User::where('email', 'john@test.com')->first();
        $this->assertTrue($john->tenants()->where('tenant_id', $this->tenant->id)->exists());

        // Verify group membership
        $group = UserGroup::where('name', 'cert-admin')->where('tenant_id', $this->tenant->id)->first();
        $this->assertTrue($john->userGroups()->where('user_groups.id', $group->id)->exists());
    }

    public function testProcessUpsertsExistingUsers(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);

        $existingUser = User::factory()->create([
            'email' => 'existing@test.com',
            'name' => 'Old Name',
            'status' => 'active',
        ]);

        $file = $this->makeCsv([
            ['Updated Name', 'existing@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        // Preview
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);
        $response->assertOk();

        // Process
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/process', [
            'removed_rows' => '[]',
        ]);

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(1, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);

        // Verify existing user was NOT duplicated (same ID)
        $this->assertDatabaseHas('users', [
            'email' => 'existing@test.com',
        ]);
        $this->assertEquals(1, User::where('email', 'existing@test.com')->count());

        // Verify tenant and group memberships were added
        $this->assertTrue($existingUser->tenants()->where('tenant_id', $this->tenant->id)->exists());
        $this->assertTrue($existingUser->userGroups()->where('user_groups.id', $group->id)->exists());
    }

    public function testProcessSkipsErrorRows(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'cert-admin'],
            ['Invalid', 'invalid@test.com', 'nonexistent-tenant', 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);
        $response->assertOk();

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/process', [
            'removed_rows' => '[]',
        ]);

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(1, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);
    }

    public function testProcessWithRemovedRows(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'cert-admin'],
            ['Jane Smith', 'jane@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);
        $response->assertOk();

        // Remove the second row
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/process', [
            'removed_rows' => json_encode([1]),
        ]);

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertEquals(1, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);

        $this->assertDatabaseHas('users', ['email' => 'john@test.com']);
        $this->assertDatabaseMissing('users', ['email' => 'jane@test.com']);
    }

    public function testProcessRequiresPreviewFirst(): void
    {
        $response = $this->actingAs($this->admin, 'web')->post('/admin/users/import/process', [
            'removed_rows' => '[]',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ===== Failed Row Download =====

    public function testDownloadFailedCsv(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'nonexistent-tenant', 'cert-admin'],
        ]);

        // Preview + Process to populate failed rows in session
        $this->actingAs($this->admin, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $this->actingAs($this->admin, 'web')->post('/admin/users/import/process', [
            'removed_rows' => '[]',
        ]);

        // Set failed rows in session manually (since Mail::fake prevents actual import)
        $this->withSession(['import_failed_rows' => [
            [
                'name' => 'John Doe',
                'email' => 'john@test.com',
                'tenant_app' => 'nonexistent-tenant',
                'user_group' => 'cert-admin',
                'remarks' => 'tenant_app does not exist',
            ],
        ]]);

        $response = $this->actingAs($this->admin, 'web')->get('/admin/users/import/failed');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('name,email,tenant_app,user_group,REMARKS', $content);
        $this->assertStringContainsString('tenant_app does not exist', $content);
    }

    // ===== Non-admin Access =====

    public function testNonAdminCannotPreview(): void
    {
        $user = User::factory()->create();
        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', $this->tenant->slug, 'cert-admin'],
        ]);

        $response = $this->actingAs($user, 'web')->post('/admin/users/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function testNonAdminCannotProcess(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->post('/admin/users/import/process', [
            'removed_rows' => '[]',
        ]);

        $response->assertStatus(403);
    }
}
