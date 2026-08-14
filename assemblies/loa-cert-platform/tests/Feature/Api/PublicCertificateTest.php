<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCertificateTest extends TestCase
{
    use RefreshDatabase;

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
        ]);

        $this->event = Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $this->template->id,
            'name' => 'SPARK Bootcamp 2026',
            'event_date' => '2026-08-15',
            'location' => 'Multipurpose Hall',
            'certificate_number_pattern' => 'CERT-####',
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);
    }

    public function test_verify_returns_active_certificate(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->getJson('/api/v1/verify/CERT-0001');

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.recipient_name', 'Maria Santos')
            ->assertJsonPath('data.event_name', 'SPARK Bootcamp 2026')
            ->assertJsonPath('data.organization.name', 'Lyceum of Alabang')
            ->assertJsonMissing(['recipient_email' => 'maria@example.com']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate.viewed',
            'source' => 'public',
        ]);
    }

    public function test_verify_returns_revoked_as_invalid(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
            'revoked_at' => now(),
            'revoke_reason' => 'Test',
        ]);

        $response = $this->getJson('/api/v1/verify/CERT-0001');

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_verify_returns_404_for_unknown_number(): void
    {
        $response = $this->getJson('/api/v1/verify/CERT-9999');

        $response->assertStatus(404);
    }

    public function test_view_returns_public_viewer_data(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->getJson("/api/v1/view/{$certificate->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.certificate.certificate_number', 'CERT-0001')
            ->assertJsonPath('data.template.name', 'Test Certificate')
            ->assertJsonPath('data.event.name', 'SPARK Bootcamp 2026')
            ->assertJsonPath('data.organization.name', 'Lyceum of Alabang')
            ->assertJsonStructure(['data' => ['certificate', 'template', 'event', 'qr_data_url', 'organization']])
            ->assertJsonMissing(['recipient_email' => 'maria@example.com']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate.viewed',
            'source' => 'public',
        ]);
    }

    public function test_view_returns_410_for_revoked(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
            'revoked_at' => now(),
            'revoke_reason' => 'Test',
        ]);

        $response = $this->getJson("/api/v1/view/{$certificate->id}");

        $response->assertStatus(410);
    }

    public function test_view_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/api/v1/view/00000000-0000-0000-0000-000000000099');

        $response->assertStatus(404);
    }
}
