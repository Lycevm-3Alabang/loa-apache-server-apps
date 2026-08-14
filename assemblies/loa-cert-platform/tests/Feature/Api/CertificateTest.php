<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\CertificateEmail;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class CertificateTest extends TestCase
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

    public function test_list_certificates(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'certificate_number', 'recipient_name', 'status'],
                ],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_certificates_by_event(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/certificates?event_id={$this->event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_certificates_by_status(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Active User',
            'recipient_email' => 'active@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Revoked User',
            'recipient_email' => 'revoked@example.com',
            'certificate_number' => 'CERT-0002',
            'revoked_at' => now(),
            'revoke_reason' => 'Test',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/certificates?status=active');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_issue_certificate(): void
    {
        $payload = [
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
        ];

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'certificate_number', 'recipient_name', 'status'],
            ])
            ->assertJsonPath('data.recipient_name', 'Maria Santos')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_issue_certificate_validates_required_fields(): void
    {
        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_name', 'recipient_email']);
    }

    public function test_issue_certificate_prevents_duplicate_active(): void
    {
        Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $payload = [
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
        ];

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', $payload);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'An active certificate already exists for this event and email.');
    }

    public function test_issue_certificate_generates_number(): void
    {
        $payload = [
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
        ];

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.certificate_number', 'CERT-0001');
    }

    public function test_get_certificate(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/certificates/{$certificate->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $certificate->id)
            ->assertJsonPath('data.certificate_number', 'CERT-0001');
    }

    public function test_get_certificate_not_found(): void
    {
        $response = $this->actingAsJwt()->getJson('/api/v1/certificates/nonexistent-id');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Certificate not found.');
    }

    public function test_revoke_certificate(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->postJson("/api/v1/certificates/{$certificate->id}/revoke", [
            'reason' => 'Administrative decision',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoke_reason', 'Administrative decision');
    }

    public function test_revoke_certificate_requires_reason(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->postJson("/api/v1/certificates/{$certificate->id}/revoke", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_revoke_already_revoked_returns_409(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
            'revoked_at' => now(),
            'revoke_reason' => 'Already revoked',
        ]);

        $response = $this->actingAsJwt()->postJson("/api/v1/certificates/{$certificate->id}/revoke", [
            'reason' => 'Try again',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Certificate is already revoked.');
    }

    public function test_delete_certificate(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->deleteJson("/api/v1/certificates/{$certificate->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('certificates', ['id' => $certificate->id]);
    }

    public function test_delete_certificate_not_found(): void
    {
        $response = $this->actingAsJwt()->deleteJson('/api/v1/certificates/nonexistent-id');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Certificate not found.');
    }

    public function test_reissue_certificate(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->postJson("/api/v1/certificates/{$certificate->id}/reissue", [
            'reason' => 'Correction',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.recipient_name', 'Test User');

        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'revoke_reason' => 'Correction',
        ]);
        $this->assertNotNull(Certificate::find($certificate->id)->revoked_at);
    }

    public function test_reissue_certificate_requires_reason(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->postJson("/api/v1/certificates/{$certificate->id}/reissue", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_email_logs(): void
    {
        $certificate = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        CertificateEmail::create([
            'certificate_id' => $certificate->id,
            'sent_to' => 'test@example.com',
            'subject' => 'Your Certificate',
            'status' => 'sent',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/certificates/{$certificate->id}/email-logs");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_expire_certificates(): void
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

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates/expire');

        $response->assertStatus(200)
            ->assertJsonPath('data.revoked', 1);
    }

    public function test_expire_certificates_dry_run(): void
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

        $response = $this->actingAsJwt()->postJson('/api/v1/certificates/expire', ['dry_run' => true]);

        $response->assertStatus(200)
            ->assertJsonPath('data.revoked', 1);

        $this->assertDatabaseHas('certificates', [
            'certificate_number' => 'CERT-0001',
            'revoked_at' => null,
        ]);
    }

    public function test_certificate_status_derivation(): void
    {
        $active = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Active',
            'recipient_email' => 'active@example.com',
            'certificate_number' => 'CERT-0001',
            'expires_at' => now()->addMonth(),
        ]);

        $expired = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Expired',
            'recipient_email' => 'expired@example.com',
            'certificate_number' => 'CERT-0002',
            'expires_at' => now()->subDay(),
        ]);

        $revoked = Certificate::create([
            'organization_id' => $this->organization->id,
            'event_id' => $this->event->id,
            'template_id' => $this->template->id,
            'recipient_name' => 'Revoked',
            'recipient_email' => 'revoked@example.com',
            'certificate_number' => 'CERT-0003',
            'revoked_at' => now(),
        ]);

        $this->assertEquals('active', $active->status);
        $this->assertEquals('expired', $expired->status);
        $this->assertEquals('revoked', $revoked->status);
    }
}
