<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class MeDashboardAuditTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private Organization $organization;
    private Event $event;
    private CertificateTemplate $template;
    private CertificateTemplate $emailTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Lyceum of Alabang',
            'slug' => 'loa',
        ]);

        config(['cert-platform.organization_id' => $this->organization->id]);

        $this->template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $this->emailTemplate = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Email',
            'type' => 'email',
            'html_content' => '<p>Hello</p>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $this->event = Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $this->template->id,
            'email_template_id' => $this->emailTemplate->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'valid_until' => now()->addMonth(),
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
        ]);
    }

    public function test_me_certificates_lists_only_own(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Admin User',
            'recipient_email' => 'admin@lyceumalabang.edu.ph',
            'certificate_number' => 'CERT-0001',
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Someone Else',
            'recipient_email' => 'other@example.com',
            'certificate_number' => 'CERT-0002',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/me/certificates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'certificate_number', 'recipient_name', 'status']],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.recipient_name', 'Admin User');
    }

    public function test_me_certificate_forbidden_when_not_owner(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Other',
            'recipient_email' => 'other@example.com',
            'certificate_number' => 'CERT-0009',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/me/certificates/{$certificate->id}");

        $response->assertStatus(403)
            ->assertJsonPath('reason', 'not_owner');
    }

    public function test_me_certificate_returns_own(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Admin User',
            'recipient_email' => 'admin@lyceumalabang.edu.ph',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/me/certificates/{$certificate->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.certificate_number', 'CERT-0001')
            ->assertJsonPath('data.event_name', 'Test Event');
    }

    public function test_me_events_scopes_to_created_by(): void
    {
        Event::create([
            'organization_id' => $this->organization->id,
            'name' => 'Someone Elses Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000099',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/me/events');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Test Event');
    }

    public function test_me_templates_scopes_to_created_by(): void
    {
        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Someone Elses Template',
            'type' => 'certificate',
            'html_content' => '<div>hi</div>',
            'created_by' => '00000000-0000-0000-0000-000000000099',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/me/templates?type=certificate');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Test Certificate');
    }

    public function test_dashboard_stats(): void
    {
        EventAttendee::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'name' => 'A',
            'email' => 'a@example.com',
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Admin User',
            'recipient_email' => 'admin@lyceumalabang.edu.ph',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'certificates' => ['total', 'active', 'revoked', 'expired', 'issued_30d'],
                    'events' => ['total', 'active'],
                    'attendees' => ['total'],
                    'templates' => ['total'],
                    'expiring_soon',
                ],
            ])
            ->assertJsonPath('data.certificates.total', 1)
            ->assertJsonPath('data.events.total', 1)
            ->assertJsonPath('data.attendees.total', 1)
            ->assertJsonPath('data.templates.total', 2);
    }

    public function test_dashboard_activity(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => 'u1',
            'user_email' => 'admin@lyceumalabang.edu.ph',
            'action' => 'certificate.issued',
            'source' => 'api',
            'entity_type' => 'certificate',
            'entity_id' => 'cert-1',
            'details' => ['certificate_number' => 'CERT-0001'],
            'created_at' => now(),
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/dashboard/activity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'action', 'entity_type', 'entity_id', 'user_email', 'created_at']],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'certificate.issued');
    }

    public function test_admin_audit_logs_list(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_email' => 'admin@lyceumalabang.edu.ph',
            'action' => 'certificate.revoked',
            'source' => 'api',
            'entity_type' => 'certificate',
            'entity_id' => 'cert-1',
            'created_at' => now(),
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'action', 'source', 'entity_type', 'entity_id', 'created_at']],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'certificate.revoked');
    }

    public function test_admin_audit_logs_filter_by_action(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_email' => 'admin@lyceumalabang.edu.ph',
            'action' => 'certificate.revoked',
            'source' => 'api',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_email' => 'admin@lyceumalabang.edu.ph',
            'action' => 'event.created',
            'source' => 'api',
            'created_at' => now(),
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/admin/audit-logs?action=certificate.revoked');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'certificate.revoked');
    }

    public function test_admin_audit_logs_export_csv(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_email' => 'admin@lyceumalabang.edu.ph',
            'action' => 'certificate.issued',
            'source' => 'api',
            'entity_type' => 'certificate',
            'entity_id' => 'cert-1',
            'created_at' => now(),
        ]);

        $response = $this->actingAsJwt()->get('/api/v1/admin/audit-logs/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition');

        $this->assertStringContainsString('certificate.issued', $response->streamedContent());
    }

    public function test_issue_records_audit_log(): void
    {
        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'event_id' => $this->event->id,
            'recipient_name' => 'Admin User',
            'recipient_email' => 'admin@lyceumalabang.edu.ph',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate.issued',
            'entity_type' => 'certificate',
            'user_email' => 'admin@lyceumalabang.edu.ph',
        ]);
    }

    public function test_revoke_records_audit_log(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Admin User',
            'recipient_email' => 'admin@lyceumalabang.edu.ph',
            'certificate_number' => 'CERT-0001',
        ]);

        $this->actingAsJwt()->postJson("/api/v1/certificates/{$certificate->id}/revoke", [
            'reason' => 'Administrative decision',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate.revoked',
            'entity_type' => 'certificate',
            'entity_id' => $certificate->id,
        ]);
    }
}
