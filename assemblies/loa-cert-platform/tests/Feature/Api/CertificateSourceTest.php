<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class CertificateSourceTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private Organization $organization;
    private Event $event;
    private CertificateTemplate $template;

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
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $this->event = Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $this->template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'valid_until' => now()->addMonth(),
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
    }

    private function createFileModeCert(string $number = 'CERT-0001'): Certificate
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Uploaded User',
            'recipient_email' => 'uploaded@example.com',
            'certificate_number' => $number,
        ]);

        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Uploaded User',
            'email' => 'uploaded@example.com',
            'certificate_id' => $certificate->id,
            'certificate_number' => $number,
            'metadata' => [
                'generation_mode' => 'file',
                'file_data' => base64_encode('%PDF-1.4 uploaded-fixture'),
                'file_name' => 'certificate.pdf',
                'file_type' => 'application/pdf',
            ],
        ]);

        return $certificate;
    }

    private function createTemplateModeCert(string $number = 'CERT-0002'): Certificate
    {
        return Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Template User',
            'recipient_email' => 'template@example.com',
            'certificate_number' => $number,
            'file_path' => 'certificates/' . $number . '.pdf',
        ]);
    }

    public function test_list_includes_generation_mode_file_for_attendee_file_mode(): void
    {
        $this->createFileModeCert();

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.generation_mode', 'file');
    }

    public function test_list_reports_template_despite_file_path_set(): void
    {
        $this->createTemplateModeCert();

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.generation_mode', 'template');
    }

    public function test_show_returns_generation_mode(): void
    {
        $certificate = $this->createFileModeCert();

        $response = $this->actingAsJwt(['groups' => ['cert-admin']])
            ->getJson("/api/v1/certificates/{$certificate->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'file');
    }

    public function test_filter_uploaded_returns_only_file_mode(): void
    {
        $this->createFileModeCert('CERT-0001');
        $this->createTemplateModeCert('CERT-0002');

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates?source=uploaded');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.generation_mode', 'file');
    }

    public function test_filter_system_generated_returns_only_template_mode(): void
    {
        $this->createFileModeCert('CERT-0001');
        $this->createTemplateModeCert('CERT-0002');

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates?source=system-generated');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.generation_mode', 'template');
    }

    public function test_omitted_source_returns_both_modes(): void
    {
        $this->createFileModeCert('CERT-0001');
        $this->createTemplateModeCert('CERT-0002');

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_source_returns_422(): void
    {
        $response = $this->actingAsJwt()->getJson('/api/v1/certificates?source=bogus');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_source_combines_and_with_event_id(): void
    {
        $otherEvent = Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $this->template->id,
            'name' => 'Other Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $this->createFileModeCert('CERT-0001');

        $other = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $otherEvent->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Other Uploaded',
            'recipient_email' => 'other-uploaded@example.com',
            'certificate_number' => 'CERT-0009',
        ]);

        EventAttendee::create([
            'event_id' => $otherEvent->id,
            'organization_id' => $this->organization->id,
            'name' => 'Other Uploaded',
            'email' => 'other-uploaded@example.com',
            'certificate_id' => $other->id,
            'certificate_number' => 'CERT-0009',
            'metadata' => ['generation_mode' => 'file'],
        ]);

        $response = $this->actingAsJwt()
            ->getJson("/api/v1/certificates?source=uploaded&event_id={$this->event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.certificate_number', 'CERT-0001');
    }

    public function test_source_combines_and_with_search_and_pagination(): void
    {
        $this->createFileModeCert('CERT-0001');
        $this->createTemplateModeCert('CERT-0002');

        $response = $this->actingAsJwt()
            ->getJson('/api/v1/certificates?source=uploaded&search=Uploaded&limit=1&offset=0');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.offset', 0)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('data.0.generation_mode', 'file');
    }

    public function test_standalone_cert_with_metadata_file_resolves_file(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => null,
            'template_id' => null,
            'recipient_name' => 'Standalone Uploaded',
            'recipient_email' => 'standalone-uploaded@example.com',
            'certificate_number' => 'CERT-0001',
            'metadata' => ['generation_mode' => 'file'],
        ]);

        $this->actingAsJwt(['groups' => ['cert-admin']])
            ->getJson("/api/v1/certificates/{$certificate->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'file');

        $this->actingAsJwt()->getJson('/api/v1/certificates?source=uploaded')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_standalone_cert_without_metadata_resolves_template(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => null,
            'template_id' => null,
            'recipient_name' => 'Standalone Template',
            'recipient_email' => 'standalone-template@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $this->actingAsJwt(['groups' => ['cert-admin']])
            ->getJson("/api/v1/certificates/{$certificate->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'template');

        $this->actingAsJwt()->getJson('/api/v1/certificates?source=system-generated')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_event_file_mode_issue_stamps_cert_and_survives_attendee_delete(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Event File User',
            'email' => 'event-file@example.com',
            'metadata' => [
                'generation_mode' => 'file',
                'file_data' => base64_encode('%PDF-1.4 event-file-fixture'),
                'file_name' => 'certificate.pdf',
                'file_type' => 'application/pdf',
            ],
        ]);

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'event_id' => $this->event->id,
            'recipient_name' => 'Event File User',
            'recipient_email' => 'event-file@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.generation_mode', 'file');

        $certificateId = $response->json('data.id');
        $this->assertEquals(
            'file',
            Certificate::find($certificateId)->metadata['generation_mode'] ?? null
        );

        $this->actingAsJwt()->deleteJson("/api/v1/attendees/{$attendee->id}")
            ->assertStatus(204);

        $this->actingAsJwt(['groups' => ['cert-admin']])
            ->getJson("/api/v1/certificates/{$certificateId}")
            ->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'file');

        $this->actingAsJwt()->getJson('/api/v1/certificates?source=uploaded')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.generation_mode', 'file');
    }

    public function test_event_template_mode_issue_resolves_template(): void
    {
        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Event Template User',
            'email' => 'event-template@example.com',
            'metadata' => ['generation_mode' => 'template'],
        ]);

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'event_id' => $this->event->id,
            'recipient_name' => 'Event Template User',
            'recipient_email' => 'event-template@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.generation_mode', 'template');

        $certificateId = $response->json('data.id');
        $this->assertEquals(
            'template',
            Certificate::find($certificateId)->metadata['generation_mode'] ?? null
        );

        $this->actingAsJwt()->getJson('/api/v1/certificates?source=system-generated')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->actingAsJwt()->getJson('/api/v1/certificates?source=uploaded')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_standalone_file_mode_via_store_metadata(): void
    {
        $standaloneTemplate = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standalone Template',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'template_id' => $standaloneTemplate->id,
            'recipient_name' => 'Standalone File User',
            'recipient_email' => 'standalone-file@example.com',
            'metadata' => ['generation_mode' => 'file'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.generation_mode', 'file');

        $certificateId = $response->json('data.id');
        $this->assertEquals(
            'file',
            Certificate::find($certificateId)->metadata['generation_mode'] ?? null
        );

        $this->actingAsJwt()->getJson('/api/v1/certificates?source=uploaded')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_standalone_file_mode_via_upload_alone_merges_metadata(): void
    {
        Storage::fake();

        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => null,
            'template_id' => $this->template->id,
            'recipient_name' => 'Upload Alone User',
            'recipient_email' => 'upload-alone@example.com',
            'certificate_number' => 'CERT-0099',
            'metadata' => ['section' => 'BSIT-3A'],
        ]);

        $file = UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf');

        $this->actingAsJwt()->post('/api/v1/certificates/upload', [
            'certificate_number' => 'CERT-0099',
            'file' => $file,
        ])->assertStatus(200);

        $fresh = $certificate->fresh();
        $this->assertEquals('file', $fresh->metadata['generation_mode'] ?? null);
        $this->assertEquals('BSIT-3A', $fresh->metadata['section'] ?? null);

        $this->actingAsJwt(['groups' => ['cert-admin']])
            ->getJson("/api/v1/certificates/{$certificate->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'file');
    }

    public function test_store_rejects_invalid_generation_mode(): void
    {
        $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'template_id' => $this->template->id,
            'recipient_name' => 'Bad Mode User',
            'recipient_email' => 'bad-mode@example.com',
            'metadata' => ['generation_mode' => 'bogus'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['metadata.generation_mode']);
    }

    public function test_store_rejects_top_level_file_path(): void
    {
        $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'template_id' => $this->template->id,
            'recipient_name' => 'Path User',
            'recipient_email' => 'path-user@example.com',
            'file_path' => 'certificates/CERT-0001.pdf',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['file_path']);
    }

    public function test_roster_edit_without_reissue_does_not_backfill_cert(): void
    {
        $attendee = EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Reissue User',
            'email' => 'reissue-user@example.com',
            'metadata' => [
                'generation_mode' => 'file',
                'file_data' => base64_encode('%PDF-1.4 reissue-fixture'),
                'file_name' => 'certificate.pdf',
                'file_type' => 'application/pdf',
            ],
        ]);

        $issue = $this->actingAsJwt()->postJson('/api/v1/certificates', [
            'event_id' => $this->event->id,
            'recipient_name' => 'Reissue User',
            'recipient_email' => 'reissue-user@example.com',
        ]);

        $issue->assertStatus(201);
        $certificateId = $issue->json('data.id');

        $this->actingAsJwt()->patchJson("/api/v1/attendees/{$attendee->id}", [
            'metadata' => ['generation_mode' => 'template'],
        ])->assertStatus(200);

        $this->assertEquals(
            'file',
            Certificate::find($certificateId)->fresh()->metadata['generation_mode'] ?? null
        );

        $this->actingAsJwt()->postJson("/api/v1/events/{$this->event->id}/reissue", [
            'attendee_ids' => [$attendee->id],
        ])->assertStatus(200);

        $reissued = Certificate::where('event_id', $this->event->id)
            ->where('recipient_email', 'reissue-user@example.com')
            ->whereNull('revoked_at')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($reissued);
        $this->assertNotEquals($certificateId, $reissued->id);
        $this->assertEquals('template', $reissued->metadata['generation_mode'] ?? null);
    }

    public function test_me_list_includes_generation_mode_for_both_modes_without_n_plus_one(): void
    {
        $owner = 'admin@lyceumalabang.edu.ph';

        $first = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Owner File User',
            'recipient_email' => $owner,
            'certificate_number' => 'CERT-0031',
        ]);

        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Owner File User',
            'email' => $owner,
            'certificate_id' => $first->id,
            'certificate_number' => 'CERT-0031',
            'metadata' => [
                'generation_mode' => 'file',
                'file_data' => base64_encode('%PDF-1.4 me-file-fixture-0'),
                'file_name' => 'certificate.pdf',
                'file_type' => 'application/pdf',
            ],
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Owner File User',
            'recipient_email' => $owner,
            'certificate_number' => 'CERT-0032',
            'metadata' => ['generation_mode' => 'file'],
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Owner Template User',
            'recipient_email' => $owner,
            'certificate_number' => 'CERT-0033',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAsJwt()->getJson('/api/v1/me/certificates');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);

        $modes = collect($response->json('data'))->pluck('generation_mode')->sort()->values()->all();
        $this->assertEquals(['file', 'file', 'template'], $modes);

        $attendeeQueries = collect(DB::getQueryLog())
            ->filter(fn ($entry) => str_contains($entry['query'], 'event_attendees'))
            ->count();
        $this->assertEquals(1, $attendeeQueries);
    }

    public function test_me_detail_returns_file_for_own_cert_and_guards_hold(): void
    {
        $owner = 'admin@lyceumalabang.edu.ph';

        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Owner User',
            'recipient_email' => $owner,
            'certificate_number' => 'CERT-0041',
        ]);

        EventAttendee::create([
            'event_id' => $this->event->id,
            'organization_id' => $this->organization->id,
            'name' => 'Owner User',
            'email' => $owner,
            'certificate_id' => $certificate->id,
            'certificate_number' => 'CERT-0041',
            'metadata' => ['generation_mode' => 'file'],
        ]);

        $other = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Someone Else',
            'recipient_email' => 'other@example.com',
            'certificate_number' => 'CERT-0042',
        ]);

        $this->actingAsJwt()->getJson("/api/v1/me/certificates/{$certificate->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.generation_mode', 'file');

        $this->actingAsJwt()->getJson("/api/v1/me/certificates/{$other->id}")
            ->assertStatus(403)
            ->assertJsonPath('reason', 'not_owner');

        $this->actingAsJwt()->getJson('/api/v1/me/certificates/00000000-0000-0000-0000-000000000099')
            ->assertStatus(404);
    }
}
