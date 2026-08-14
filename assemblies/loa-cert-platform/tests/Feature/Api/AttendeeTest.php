<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class AttendeeTest extends TestCase
{
    use RefreshDatabase, WithJwt;

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

    public function test_list_attendees(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->actingAsJwt()->getJson("/api/v1/events/{$this->event->id}/attendees")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'attended', 'completed'],
                ],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_list_attendees_filters_by_search(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->actingAsJwt()->getJson("/api/v1/events/{$this->event->id}/attendees?search=Maria")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_attendees_event_not_found(): void
    {
        $this->actingAsJwt()->getJson('/api/v1/events/nonexistent-id/attendees')
            ->assertStatus(404);
    }

    public function test_create_attendee(): void
    {
        $response = $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees", [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'attended' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'event_id', 'name', 'email', 'attended', 'completed']])
            ->assertJsonPath('data.name', 'Maria Santos')
            ->assertJsonPath('data.event_id', $this->event->id);

        $this->assertDatabaseHas('event_attendees', [
            'event_id' => $this->event->id,
            'email' => 'maria@example.com',
            'name' => 'Maria Santos',
            'attended' => true,
        ]);
    }

    public function test_create_attendee_upserts_by_email(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees", [
            'name' => 'New Name',
            'email' => 'maria@example.com',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseCount('event_attendees', 1);
        $this->assertDatabaseHas('event_attendees', [
            'event_id' => $this->event->id,
            'email' => 'maria@example.com',
            'name' => 'New Name',
        ]);
    }

    public function test_create_attendee_validates_required_fields(): void
    {
        $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_create_attendee_event_not_found(): void
    {
        $this->actingAsJwt()->postJson('/api/v1/events/nonexistent-id/attendees', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
        ])
            ->assertStatus(404);
    }

    public function test_update_attendee(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->patchJson("/api/v1/attendees/{$attendee->id}", [
            'name' => 'Maria G. Santos',
            'completed' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Maria G. Santos')
            ->assertJsonPath('data.completed', true);
    }

    public function test_update_attendee_email_conflict(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $other = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->actingAsJwt()->patchJson("/api/v1/attendees/{$other->id}", [
            'email' => 'maria@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_delete_attendee(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->deleteJson("/api/v1/attendees/{$attendee->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('event_attendees', ['id' => $attendee->id]);
    }

    public function test_delete_attendee_with_cert(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $attendee->update(['certificate_id' => $certificate->id]);

        $this->actingAsJwt()->deleteJson("/api/v1/attendees/{$attendee->id}/with-cert")
            ->assertStatus(204);

        $this->assertDatabaseMissing('event_attendees', ['id' => $attendee->id]);
        $this->assertDatabaseMissing('certificates', ['id' => $certificate->id]);
    }

    public function test_delete_preview_no_certificate(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->getJson("/api/v1/attendees/{$attendee->id}/delete-preview")
            ->assertStatus(200)
            ->assertJsonPath('data.attendee_id', $attendee->id)
            ->assertJsonPath('data.linked_certificate', null)
            ->assertJsonPath('data.deletes_certificate', false);
    }

    public function test_delete_preview_with_certificate(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $attendee->update(['certificate_id' => $certificate->id]);

        $this->actingAsJwt()->getJson("/api/v1/attendees/{$attendee->id}/delete-preview")
            ->assertStatus(200)
            ->assertJsonPath('data.linked_certificate.id', $certificate->id)
            ->assertJsonPath('data.deletes_certificate', true);
    }

    public function test_file_data_defaults_to_template_mode(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->getJson("/api/v1/attendees/{$attendee->id}/file-data")
            ->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'template');
    }

    public function test_file_data_file_removed_returns_410(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'metadata' => [
                'generation_mode' => 'file',
                'file_path' => 'certificates/missing.pdf',
            ],
        ]);

        $this->actingAsJwt()->getJson("/api/v1/attendees/{$attendee->id}/file-data")
            ->assertStatus(410);
    }

    public function test_import_attendees(): void
    {
        $response = $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees/import", [
            'attendees' => [
                ['name' => 'Maria Santos', 'email' => 'maria@example.com'],
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.skipped', 0);

        $this->assertDatabaseCount('event_attendees', 2);
    }

    public function test_import_attendees_merge_upserts_by_email(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees/import", [
            'attendees' => [
                ['name' => 'New Name', 'email' => 'maria@example.com'],
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseCount('event_attendees', 1);
        $this->assertDatabaseHas('event_attendees', [
            'email' => 'maria@example.com',
            'name' => 'New Name',
        ]);
    }

    public function test_import_replace_requires_confirm(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
            'email' => 'maria@example.com',
        ]);

        $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees/import", [
            'attendees' => [
                ['name' => 'New Name', 'email' => 'new@example.com'],
            ],
            'mode' => 'replace',
        ])
            ->assertStatus(422);
    }

    public function test_import_replace_with_confirm(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/attendees/import", [
            'attendees' => [
                ['name' => 'New Name', 'email' => 'new@example.com'],
            ],
            'mode' => 'replace',
            'confirm' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseMissing('event_attendees', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('event_attendees', ['email' => 'new@example.com']);
    }
}
