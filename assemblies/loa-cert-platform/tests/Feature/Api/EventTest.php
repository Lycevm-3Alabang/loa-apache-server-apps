<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\CertificateSequence;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private CertificateTemplate $template;
    private Event $event;

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
        ]);

        $this->event = Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $this->template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);
    }

    public function test_list_events(): void
    {
        Event::create([
            'organization_id' => $this->organization->id,
            'name' => 'Second Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'attendees_count', 'certificates_issued'],
                ],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_list_events_filters_by_status_and_search(): void
    {
        $this->getJson('/api/v1/events?status=active&search=Test')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/events?status=archive')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_create_event(): void
    {
        $response = $this->postJson('/api/v1/events', [
            'name' => 'Graduation 2026',
            'certificate_number_pattern' => 'GRAD-####',
            'event_date' => '2026-05-30',
            'location' => 'Alabang',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'certificate_number_pattern', 'attendees_count', 'certificates_issued'],
            ])
            ->assertJsonPath('data.name', 'Graduation 2026')
            ->assertJsonPath('data.certificate_number_pattern', 'GRAD-####')
            ->assertJsonPath('data.attendees_count', 0);
    }

    public function test_create_event_validates_required_fields(): void
    {
        $this->postJson('/api/v1/events', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'certificate_number_pattern']);
    }

    public function test_get_event(): void
    {
        $this->getJson("/api/v1/events/{$this->event->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->event->id)
            ->assertJsonPath('data.attendees_count', 0);
    }

    public function test_get_event_not_found(): void
    {
        $this->getJson('/api/v1/events/nonexistent-id')
            ->assertStatus(404);
    }

    public function test_update_event(): void
    {
        $this->patchJson("/api/v1/events/{$this->event->id}", [
            'name' => 'Updated Event Name',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Event Name');

        $this->assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => 'Updated Event Name',
        ]);
    }

    public function test_delete_event(): void
    {
        $this->deleteJson("/api/v1/events/{$this->event->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('events', ['id' => $this->event->id]);
    }

    public function test_event_stats(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'attended' => true,
            'completed' => true,
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Active',
            'recipient_email' => 'active@example.com',
            'certificate_number' => 'CERT-0001',
            'expires_at' => now()->addDays(10),
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Revoked',
            'recipient_email' => 'revoked@example.com',
            'certificate_number' => 'CERT-0002',
            'revoked_at' => now(),
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Expired',
            'recipient_email' => 'expired@example.com',
            'certificate_number' => 'CERT-0003',
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/events/{$this->event->id}/stats")
            ->assertStatus(200)
            ->assertJsonPath('data.attendees.total', 1)
            ->assertJsonPath('data.attendees.attended', 1)
            ->assertJsonPath('data.attendees.completed', 1)
            ->assertJsonPath('data.certificates.issued', 3)
            ->assertJsonPath('data.certificates.active', 1)
            ->assertJsonPath('data.certificates.revoked', 1)
            ->assertJsonPath('data.certificates.expired', 1)
            ->assertJsonPath('data.expiring', 1);
    }

    public function test_clone_template(): void
    {
        $source = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Source Certificate',
            'type' => 'certificate',
            'html_content' => '<div>Source</div>',
            'css_content' => 'body {}',
        ]);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/clone-template", [
            'source_template_id' => $source->id,
            'name' => 'Cloned Certificate',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['template_id', 'name']])
            ->assertJsonPath('data.name', 'Cloned Certificate');

        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'Cloned Certificate',
            'type' => 'certificate',
            'html_content' => '<div>Source</div>',
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $this->event->id,
            'template_id' => $response->json('data.template_id'),
        ]);
    }

    public function test_clone_template_not_found(): void
    {
        $emailSource = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Some Email',
            'type' => 'email',
            'html_content' => '<div>Email</div>',
        ]);

        $this->postJson("/api/v1/events/{$this->event->id}/clone-template", [
            'source_template_id' => $emailSource->id,
            'name' => 'Cloned',
        ])
            ->assertStatus(404);
    }

    public function test_clone_email_template(): void
    {
        $source = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Source Email',
            'type' => 'email',
            'html_content' => '<div>Hello {{recipient_name}}</div>',
        ]);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/clone-email-template", [
            'source_template_id' => $source->id,
            'name' => 'Cloned Email',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Cloned Email');

        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'Cloned Email',
            'type' => 'email',
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $this->event->id,
            'email_template_id' => $response->json('data.template_id'),
        ]);
    }

    public function test_bulk_issue(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/bulk-issue", [
            'attendee_ids' => [$attendee->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 0);

        $certificateId = $response->json('data.certificates.0');

        $this->assertDatabaseHas('certificates', [
            'id' => $certificateId,
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $this->assertDatabaseHas('event_attendees', [
            'id' => $attendee->id,
            'certificate_id' => $certificateId,
        ]);
    }

    public function test_bulk_issue_skips_attendee_with_active_certificate(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $this->postJson("/api/v1/events/{$this->event->id}/bulk-issue", [
            'attendee_ids' => [$attendee->id],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.success', 0)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.errors.0.reason', 'Active certificate already exists');
    }

    public function test_bulk_issue_validates_attendee_ids(): void
    {
        $this->postJson("/api/v1/events/{$this->event->id}/bulk-issue", [
            'attendee_ids' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attendee_ids']);
    }

    public function test_issue_completed(): void
    {
        $completed = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Completed User',
            'email' => 'completed@example.com',
            'completed' => true,
        ]);

        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'completed' => false,
        ]);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/issue-completed");

        $response->assertStatus(200)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 0);

        $certificateId = $response->json('data.certificates.0');

        $this->assertDatabaseHas('event_attendees', [
            'id' => $completed->id,
            'certificate_id' => $certificateId,
        ]);

        $this->assertDatabaseMissing('certificates', [
            'recipient_email' => 'pending@example.com',
        ]);
    }

    public function test_reissue(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $oldCertificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $attendee->update([
            'certificate_id' => $oldCertificate->id,
            'certificate_number' => 'CERT-0001',
        ]);

        CertificateSequence::create([
            'organization_id' => $this->organization->id,
            'pattern' => $this->event->certificate_number_pattern,
            'next_value' => 2,
        ]);

        $response = $this->postJson("/api/v1/events/{$this->event->id}/reissue", [
            'attendee_ids' => [$attendee->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', 1);

        $this->assertDatabaseHas('certificates', [
            'id' => $oldCertificate->id,
            'revoke_reason' => 'Reissued',
        ]);
        $this->assertDatabaseHas('certificates', [
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0002',
            'revoked_at' => null,
        ]);
    }

    public function test_revoke_expired_count(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Expired User',
            'recipient_email' => 'expired@example.com',
            'certificate_number' => 'CERT-0001',
            'expires_at' => now()->subDay(),
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Active User',
            'recipient_email' => 'active@example.com',
            'certificate_number' => 'CERT-0002',
            'expires_at' => now()->addMonth(),
        ]);

        $this->getJson("/api/v1/events/{$this->event->id}/revoke-expired")
            ->assertStatus(200)
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.expired', 1);
    }

    public function test_revoke_expired(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Expired User',
            'recipient_email' => 'expired@example.com',
            'certificate_number' => 'CERT-0001',
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/events/{$this->event->id}/revoke-expired")
            ->assertStatus(200)
            ->assertJsonPath('data.revoked', 1);

        $this->assertDatabaseHas('certificates', [
            'certificate_number' => 'CERT-0001',
            'revoke_reason' => 'Auto-expired',
        ]);
    }
}
