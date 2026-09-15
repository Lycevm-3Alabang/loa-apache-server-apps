<?php

namespace Tests\Unit;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Services\PlaceholderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceholderResolverTest extends TestCase
{
    use RefreshDatabase;

    private PlaceholderResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PlaceholderResolver::class);
    }

    public function test_resolves_recipient_name(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{recipient_name}}', $certificate);

        $this->assertEquals('Maria Santos', $result);
    }

    public function test_resolves_certificate_number(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{certificate_number}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{certificate_number}}', $certificate);

        $this->assertEquals('CERT-0001', $result);
    }

    public function test_resolves_event_name(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{event_name}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'SPARK Bootcamp 2026',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{event_name}}', $certificate);

        $this->assertEquals('SPARK Bootcamp 2026', $result);
    }

    public function test_resolves_organization_name(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{organization_name}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'organizer' => 'SAO',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{organization_name}}', $certificate);

        $this->assertEquals('SAO', $result);
    }

    public function test_resolves_event_organizer(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{event_organizer}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'organizer' => 'CCS',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{event_organizer}}', $certificate);

        $this->assertEquals('CCS', $result);
    }

    public function test_resolves_certificate_title(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{certificate_title}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_title' => 'Certificate of Completion',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{certificate_title}}', $certificate);

        $this->assertEquals('Certificate of Completion', $result);
    }

    public function test_resolves_expiry_date(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{expiry_date}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
            'expires_at' => '2026-12-31',
        ]);

        $result = $this->resolver->resolve('{{expiry_date}}', $certificate);

        $this->assertEquals('December 31, 2026', $result);
    }

    public function test_resolves_multiple_placeholders(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}} - {{certificate_number}}</div>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);
        $certificate = Certificate::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'template_id' => $template->id,
            'recipient_name' => 'Maria Santos',
            'recipient_email' => 'maria@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $result = $this->resolver->resolve('{{recipient_name}} - {{certificate_number}}', $certificate);

        $this->assertEquals('Maria Santos - CERT-0001', $result);
    }
}
