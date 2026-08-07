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
        $this->resolver = new PlaceholderResolver();
    }

    public function test_resolves_recipient_name(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
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
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
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
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
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

        $this->assertEquals('Lyceum of Alabang', $result);
    }

    public function test_resolves_multiple_placeholders(): void
    {
        $organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        $template = CertificateTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Test',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}} - {{certificate_number}}</div>',
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
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

        $result = $this->resolver->resolve('{{recipient_name}} - {{certificate_number}}', $certificate);

        $this->assertEquals('Maria Santos - CERT-0001', $result);
    }
}
