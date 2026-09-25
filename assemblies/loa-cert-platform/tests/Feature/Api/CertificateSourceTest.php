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
}
