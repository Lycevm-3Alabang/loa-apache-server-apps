<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantMemberImportTest extends TestCase
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

    private function createGroup(string $name, ?Tenant $tenant = null): UserGroup
    {
        return UserGroup::create([
            'name' => $name,
            'description' => "Test group: {$name}",
            'priority' => 10,
            'tenant_id' => $tenant?->id,
        ]);
    }

    private function makeCsv(array $rows): UploadedFile
    {
        $content = "name,email,user_group\n";
        foreach ($rows as $row) {
            $content .= implode(',', $row) . "\n";
        }

        return UploadedFile::fake()->createWithContent('members.csv', $content);
    }

    private function makeRawCsv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('members.csv', $content);
    }

    // ===== CSV parsing edge cases =====

    public function testPreviewParsesQuotedFieldsWithCommasInName(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeRawCsv(
            "name,email,user_group\n" .
            "\"ALamo, Nino Francisco\",\"alamoninofrancisco@gmail.com\",\"cert-admin\"\n"
        );

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertEquals('ALamo, Nino Francisco', $rows[0]['name']);
        $this->assertEquals('alamoninofrancisco@gmail.com', $rows[0]['email']);
        $this->assertEquals('cert-admin', $rows[0]['user_group']);
        $this->assertEquals('ready', $rows[0]['status']);
    }

    public function testPreviewAcceptsHyphenatedUserGroupHeader(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeRawCsv(
            "name,email,user-group\n" .
            'John Doe,john@test.com,cert-admin'
        );

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertEquals('ready', $rows[0]['status']);
    }

    public function testPreviewToleratesTrailingCommasAndBlankLines(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeRawCsv(
            "name,email,user_group\n" .
            "John Doe,john@test.com,cert-admin,\n" .
            "\n" .
            '"Jane Smith", jane@test.com , cert-admin ,'
        );

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertCount(2, $rows);
        $this->assertEquals('ready', $rows[0]['status']);
        $this->assertEquals('ready', $rows[1]['status']);
        $this->assertEquals('cert-admin', $rows[1]['user_group']);
    }

    public function testFormRequiresAdmin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get("/admin/tenants/{$this->tenant->id}/members/import");

        $response->assertStatus(403);
    }

    public function testAdminCanSeeFormWithTenantGroups(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$this->tenant->id}/members/import");

        $response->assertOk();
        $response->assertViewHas('tenant', fn ($t) => $t->id === $this->tenant->id);
        $response->assertViewHas('groups');
    }

    public function testPreviewInjectsTenantAndValidates(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertEquals('ready', $rows[0]['status']);
        $this->assertEquals($this->tenant->slug, $rows[0]['tenant_app']);
    }

    public function testPreviewRejectsInvalidHeaders(): void
    {
        $content = "name,email,tenant_app\nJohn,john@test.com,x\n";
        $file = UploadedFile::fake()->createWithContent('members.csv', $content);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function testPreviewRejectsGroupFromAnotherTenant(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $this->createGroup('other-group', $otherTenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'other-group'],
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertEquals('error', $rows[0]['status']);
        $this->assertStringContainsString('user_group does not exist', $rows[0]['remarks']);
    }

    public function testPreviewDetectsDuplicateEmail(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'cert-admin'],
            ['John Two', 'john@test.com', 'cert-admin'],
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertEquals('ready', $rows[0]['status']);
        $this->assertEquals('error', $rows[1]['status']);
    }

    public function testProcessImportsNewUsers(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'cert-admin'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
            ]);

        $response->assertOk();
        $summary = $response->viewData('summary');

        $this->assertEquals(1, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);

        $john = User::where('email', 'john@test.com')->first();
        $this->assertNotNull($john);
        $this->assertEquals('pending', $john->status);

        $this->assertTrue($john->tenants()->whereKey($this->tenant->id)->exists());
        $this->assertTrue($john->userGroups()->whereKey($group->id)->exists());
    }

    public function testProcessCreatesActivationOnlyForNewUsers(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        User::factory()->create([
            'email' => 'existing@test.com',
            'status' => 'active',
        ]);

        $file = $this->makeCsv([
            ['New Guy', 'new@test.com', 'cert-admin'],
            ['Existing User', 'existing@test.com', 'cert-admin'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
            ]);

        $summary = $response->viewData('summary');
        $this->assertEquals(2, $summary['successful']);

        $newUserId = User::where('email', 'new@test.com')->value('id');
        $existingUserId = User::where('email', 'existing@test.com')->value('id');

        $this->assertDatabaseCount('activations', 1);
        $this->assertDatabaseHas('activations', ['user_id' => $newUserId]);
        $this->assertDatabaseMissing('activations', ['user_id' => $existingUserId]);
    }

    public function testProcessSkipsAlreadyInGroup(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);

        $member = User::factory()->create(['status' => 'active']);
        $member->tenants()->attach($this->tenant->id);
        $member->userGroups()->attach($group->id);
        $originalPivotCount = $member->userGroups()->count();

        $file = $this->makeCsv([
            ['Member', $member->email, 'cert-admin'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
            ]);

        $summary = $response->viewData('summary');

        $this->assertEquals(1, $summary['successful']);
        $this->assertEquals($originalPivotCount, $member->userGroups()->count());
    }

    public function testProcessMovesExistingMemberToTargetGroup(): void
    {
        $groupA = $this->createGroup('cert-user', $this->tenant);
        $groupB = $this->createGroup('cert-staff', $this->tenant);

        $member = User::factory()->create(['status' => 'active']);
        $member->tenants()->attach($this->tenant->id);
        $member->userGroups()->attach($groupA->id);

        $file = $this->makeCsv([
            ['Member', $member->email, 'cert-staff'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
            ]);

        $summary = $response->viewData('summary');

        $this->assertEquals(1, $summary['successful']);
        $this->assertTrue($member->userGroups()->whereKey($groupB->id)->exists());
        // Multi-group import is additive: old group is NOT removed
        $this->assertTrue($member->userGroups()->whereKey($groupA->id)->exists());
    }

    public function testProcessBlockedForInactiveTenant(): void
    {
        $this->createGroup('cert-admin', $this->tenant);
        $this->tenant->update(['status' => 'suspended']);

        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'cert-admin'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
            ], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'john@test.com']);
    }

    public function testDownloadFailedCsvHeaders(): void
    {
        $this->withSession(['tenant_member_import_failed_rows' => [
            [
                'name' => 'John Doe',
                'email' => 'john@test.com',
                'user_group' => 'ghost-group',
                'remarks' => 'user_group does not exist',
            ],
        ]]);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$this->tenant->id}/members/import/failed");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('name,email,user_group,REMARKS', $content);
        $this->assertStringContainsString('user_group does not exist', $content);
    }

    // ===== Field length limits =====

    public function testPreviewRejectsOverlongFields(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $longEmail = str_repeat('a', 250) . '@example.com';
        $this->assertGreaterThan(255, strlen($longEmail));

        $file = $this->makeRawCsv(
            "name,email,user_group\n" .
            str_repeat('N', 256) . ",short@example.com,cert-admin\n" .
            "Ok Name,{$longEmail},cert-admin\n" .
            "Group User,groupuser@example.com," . str_repeat('G', 256) . "\n"
        );

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');

        $this->assertEquals('error', $rows[0]['status']);
        $this->assertStringContainsString('name is too long', $rows[0]['remarks']);

        $this->assertEquals('error', $rows[1]['status']);
        $this->assertStringContainsString('email is too long', $rows[1]['remarks']);

        $this->assertEquals('error', $rows[2]['status']);
        $this->assertStringContainsString('user_group is too long', $rows[2]['remarks']);
    }

    public function testPreviewAcceptsMaxValidLengthFields(): void
    {
        $this->createGroup(str_repeat('g', 255), $this->tenant);

        // RFC 5321 caps addresses at 254 chars (PHP filter_var enforces this),
        // even though the users.email column allows 255.
        $maxEmail = str_repeat('a', 64) . '@' . str_repeat('b', 61) . '.' . str_repeat('c', 61) . '.' . str_repeat('d', 61) . '.com';
        $this->assertEquals(254, strlen($maxEmail));

        $file = $this->makeCsv([
            [str_repeat('n', 255), $maxEmail, str_repeat('g', 255)],
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertOk();
        $rows = $response->viewData('rows');
        $this->assertEquals('ready', $rows[0]['status']);
    }

    // ===== Volume caps =====

    public function testPreviewRejectsFilesOverMaxRows(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $lines = ["name,email,user_group"];
        for ($i = 0; $i < 5001; $i++) {
            $lines[] = "User {$i},bulk{$i}@test.com,cert-admin";
        }

        $file = $this->makeRawCsv(implode("\n", $lines));

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function testProcessHandlesFiveHundredNewUsers(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);

        $lines = ["name,email,user_group"];
        for ($i = 0; $i < 500; $i++) {
            $lines[] = "\"Bulk User {$i}\",\"bulk{$i}@test.com\",cert-admin";
        }
        $file = $this->makeRawCsv(implode("\n", $lines));

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
            ]);

        $summary = $response->viewData('summary');

        $this->assertEquals(500, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);
        $this->assertEquals(500, DB::table('user_tenants')->where('tenant_id', $this->tenant->id)->count());
        $this->assertTrue(
            User::where('email', 'bulk499@test.com')->first()
                ->userGroups()->whereKey($group->id)->exists()
        );
    }

    // ===== Chunked / resumable processing =====

    private function ajaxProcess(Tenant $tenant, int $cursor = 0): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/process", [
                'removed_rows' => '[]',
                'cursor' => $cursor,
            ], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    public function testAjaxProcessRunsInBatchesUntilDone(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);

        $lines = ["name,email,user_group"];
        for ($i = 0; $i < 120; $i++) {
            $lines[] = "Chunk User {$i},chunk{$i}@test.com,cert-admin";
        }
        $file = $this->makeRawCsv(implode("\n", $lines));

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $cursor = 0;
        $ticks = 0;
        $totalProcessed = 0;

        do {
            $response = $this->ajaxProcess($this->tenant, $cursor);
            $response->assertOk();

            $data = $response->json();
            $this->assertEquals('applied', $data['status']);
            $this->assertLessThanOrEqual(50, $data['processed']);

            $cursor = $data['next_cursor'];
            $totalProcessed += $data['processed'];
            $ticks++;
        } while (!$data['done'] && $ticks < 10);

        $this->assertTrue($data['done']);
        $this->assertEquals(3, $ticks);
        $this->assertEquals(120, $totalProcessed);
        $this->assertEquals(120, DB::table('user_tenants')->where('tenant_id', $this->tenant->id)->count());

        $sample = User::where('email', 'chunk119@test.com')->first();
        $this->assertTrue($sample->userGroups()->whereKey($group->id)->exists());
    }

    public function testInterruptedRunLeavesSessionIntactForResume(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $lines = ["name,email,user_group"];
        for ($i = 0; $i < 60; $i++) {
            $lines[] = "Resume User {$i},resume{$i}@test.com,cert-admin";
        }
        $file = $this->makeRawCsv(implode("\n", $lines));

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        // First batch only (simulated crash after 50 rows)
        $first = $this->ajaxProcess($this->tenant, 0)->json();

        $this->assertFalse($first['done']);
        $this->assertEquals(50, $first['next_cursor']);
        $this->assertNotNull(session('tenant_member_import_rows'));

        // Resume from cursor
        $second = $this->ajaxProcess($this->tenant, $first['next_cursor'])->json();

        $this->assertTrue($second['done']);
        $this->assertEquals(60, User::where('email', 'like', 'resume%@test.com')->count());
        $this->assertNull(session('tenant_member_import_rows'));
    }

    public function testReRunningCompletedImportIsIdempotent(): void
    {
        $group = $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['Idem One', 'idem1@test.com', 'cert-admin'],
            ['Idem Two', 'idem2@test.com', 'cert-admin'],
        ]);

        foreach ([0, 1] as $run) {
            $this->actingAs($this->admin, 'web')
                ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                    'file' => $file,
                ]);

            $response = $this->ajaxProcess($this->tenant, 0);

            $this->assertTrue($response->json('done'));
            $this->assertEquals(2, $response->json('processed'));
        }

        $this->assertEquals(2, User::where('email', 'like', 'idem%@test.com')->count());

        $member = User::where('email', 'idem1@test.com')->first();
        $this->assertSame(1, $member->userGroups()->count());
    }

    // ===== Pending import banner / discard =====

    public function testShowPageShowsPendingBannerAfterPreview(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['Banner One', 'banner1@test.com', 'cert-admin'],
            ['Banner Two', 'banner2@test.com', 'cert-admin'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$this->tenant->id}");

        $response->assertOk();
        $response->assertSee('Pending member import');
        $response->assertSee('"members.csv"');
        $response->assertSee('2 rows waiting');
    }

    public function testNoBannerWhenNothingPending(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$this->tenant->id}");

        $response->assertOk();
        $response->assertDontSee('Pending member import');
    }

    public function testDiscardClearsPendingRowsAndBlocksProcess(): void
    {
        $this->createGroup('cert-admin', $this->tenant);

        $file = $this->makeCsv([
            ['Gone One', 'gone1@test.com', 'cert-admin'],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/discard");

        $response->assertRedirect();
        $this->assertNull(session('tenant_member_import_rows'));

        $ajax = $this->ajaxProcess($this->tenant, 0);
        $ajax->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'gone1@test.com']);
    }

    public function testNonAdminCannotPreview(): void
    {
        $user = User::factory()->create();
        $file = $this->makeCsv([
            ['John Doe', 'john@test.com', 'cert-admin'],
        ]);

        $response = $this->actingAs($user, 'web')
            ->post("/admin/tenants/{$this->tenant->id}/members/import/preview", [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }
}
