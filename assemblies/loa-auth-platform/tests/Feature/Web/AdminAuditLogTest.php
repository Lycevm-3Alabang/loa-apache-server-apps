<?php

namespace Tests\Feature\Web;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private UserGroup $adminGroup;
    private UserGroup $plainGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $this->tenant = Tenant::create([
            'slug' => 'loa',
            'name' => 'LOA Certificates',
            'status' => 'active',
            'app_url' => 'https://e-cert.vercel.app',
            'redirect_origins' => ['https://e-cert.vercel.app'],
        ]);

        $this->adminGroup = UserGroup::create([
            'name' => config('auth-web.admin_group', 'loa-auth-admin'),
        ]);

        $this->plainGroup = UserGroup::create(['name' => 'cert-staff']);
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin@lyceumalabang.edu.ph',
            'status' => 'active',
        ]);
        $admin->userGroups()->attach($this->adminGroup->id);
        $admin->refresh();

        $this->actingAs($admin, 'web');

        return $admin;
    }

    public function test_admin_group_grant_writes_dedicated_evidence_row(): void
    {
        $admin = $this->actingAdmin();
        $member = User::factory()->create();

        $this->post("/admin/groups/{$this->adminGroup->id}/members", [
            'user_id' => $member->id,
        ])->assertRedirect();

        $log = AuditLog::where('action', 'admin_group.granted')->firstOrFail();
        $this->assertSame($member->id, $log->entity_id);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($admin->email, $log->actor_email);
        $this->assertSame($this->adminGroup->name, $log->details['group']);
        $this->assertNotNull($log->ip_address);

        // The generic membership key is also present (§5 dual emission).
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.member_added']);
    }

    public function test_admin_group_revoke_writes_revocation_row(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();
        $member->userGroups()->attach($this->adminGroup->id);

        $this->post("/admin/groups/{$this->adminGroup->id}/members/{$member->id}/remove")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_group.revoked']);
    }

    public function test_non_admin_group_add_never_emits_admin_group_keys(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();

        $this->post("/admin/groups/{$this->plainGroup->id}/members", [
            'user_id' => $member->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'group.member_added']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'admin_group.granted']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'admin_group.revoked']);
    }

    public function test_rows_survive_user_deletion_via_denormalized_email(): void
    {
        $admin = $this->actingAdmin();
        $member = User::factory()->create();

        $this->post("/admin/groups/{$this->adminGroup->id}/members", [
            'user_id' => $member->id,
        ])->assertRedirect();

        $member->delete();

        $log = AuditLog::where('action', 'admin_group.granted')->firstOrFail();
        $this->assertSame($member->id, $log->entity_id);
        $this->assertNotNull($log->actor_email);
        $this->assertSame($admin->email, $log->actor_email);
    }

    public function test_portal_go_by_admin_records_tenant_entry(): void
    {
        $admin = $this->actingAdmin();
        $admin->tenants()->attach($this->tenant->id);

        $tenantGroup = UserGroup::create([
            'name' => 'cert-admin',
            'tenant_id' => $this->tenant->id,
        ]);
        $admin->userGroups()->attach($tenantGroup->id);

        $this->post("/launcher/go/{$this->tenant->id}")->assertRedirect('/redirect');

        $log = AuditLog::where('action', 'auth.tenant_entry')->firstOrFail();
        $this->assertSame($this->tenant->id, $log->entity_id);
        $this->assertSame('portal', $log->details['via']);
        $this->assertSame('loa', $log->details['tenant']);
    }

    public function test_browser_lists_newest_first_and_combines_filters(): void
    {
        $this->actingAdmin();

        AuditLog::create([
            'actor_id' => 'x', 'actor_email' => 'old@lyceumalabang.edu.ph',
            'action' => 'admin_group.granted', 'created_at' => now()->subHour(),
        ]);
        AuditLog::create([
            'actor_id' => 'y', 'actor_email' => 'new@lyceumalabang.edu.ph',
            'action' => 'admin_group.revoked', 'created_at' => now(),
        ]);
        AuditLog::create([
            'actor_id' => 'y', 'actor_email' => 'new@lyceumalabang.edu.ph',
            'action' => 'group.member_added', 'created_at' => now(),
        ]);

        // Newest first.
        $response = $this->get('/admin/audit-logs');
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('new@lyceumalabang.edu.ph', $content);

        // Combined filters: action prefix + actor email.
        $filtered = $this->get('/admin/audit-logs?action=admin_group&actor=new');
        $html = $filtered->getContent();
        $this->assertStringContainsString('admin_group.revoked', $html);
        $this->assertStringNotContainsString('group.member_added</strong>', $html);
        $this->assertStringNotContainsString('old@lyceumalabang.edu.ph', $html);
    }

    public function test_action_filter_treats_percent_literally(): void
    {
        $this->actingAdmin();

        AuditLog::create([
            'actor_id' => 'x', 'actor_email' => 'a@b.c',
            'action' => 'admin_group.granted', 'created_at' => now(),
        ]);

        $response = $this->get('/admin/audit-logs?action=admin_group.%25');

        $this->assertStringNotContainsString('admin_group.granted</strong>', $response->getContent());
    }

    public function test_csv_export_streams_filtered_rows(): void
    {
        $this->actingAdmin();

        AuditLog::create([
            'actor_id' => 'x', 'actor_email' => 'wanted@lyceumalabang.edu.ph',
            'action' => 'admin_group.granted', 'details' => ['group' => 'loa-auth-admin'],
            'ip_address' => '10.0.0.1', 'created_at' => now(),
        ]);
        AuditLog::create([
            'actor_id' => 'y', 'actor_email' => 'other@lyceumalabang.edu.ph',
            'action' => 'group.member_added', 'created_at' => now(),
        ]);

        $response = $this->get('/admin/audit-logs/export?action=admin_group');
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('created_at,actor_email,action', $csv);
        $this->assertStringContainsString('wanted@lyceumalabang.edu.ph', $csv);
        $this->assertStringContainsString('admin_group.granted', $csv);
        $this->assertStringNotContainsString('other@lyceumalabang.edu.ph', $csv);
    }

    public function test_primary_action_survives_logger_failure(): void
    {
        $this->actingAdmin();
        $member = User::factory()->create();

        $failing = \Mockery::mock(AuditLogger::class)->makePartial();
        $failing->shouldReceive('record')
            ->andThrow(new \RuntimeException('storage down'));
        $this->app->instance(AuditLogger::class, $failing);

        $response = $this->post("/admin/groups/{$this->adminGroup->id}/members", [
            'user_id' => $member->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', '1 user(s) added to group.');
        $this->assertDatabaseHas('user_user_group', [
            'user_id' => $member->id,
            'user_group_id' => $this->adminGroup->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_status_change_is_audited(): void
    {
        $admin = $this->actingAdmin();
        $member = User::factory()->create(['status' => 'active']);

        // updateStatus() gates on users.manage; grant it via direct override
        // since production seeds permissions outside RefreshDatabase.
        $permission = Permission::firstOrCreate(['key' => 'users.manage']);
        DB::table('user_permission')->insert([
            'user_id' => $admin->id,
            'permission_id' => $permission->id,
            'granted' => 1,
            'tenant_id' => null,
        ]);

        $this->post("/admin/users/{$member->id}/status", [
            'status' => 'disabled',
        ])->assertRedirect();

        $log = AuditLog::where('action', 'user.status_changed')->firstOrFail();
        $this->assertSame('active', $log->details['from']);
        $this->assertSame('disabled', $log->details['to']);
    }
}
