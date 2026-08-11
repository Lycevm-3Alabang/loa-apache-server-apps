<?php

namespace Tests\Feature\Api;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class CertificateTemplateTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Lyceum of Alabang',
            'slug' => 'loa',
        ]);

        config(['cert-platform.organization_id' => $this->organization->id]);
    }

    public function test_list_templates(): void
    {
        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
        ]);

        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Email',
            'type' => 'email',
            'html_content' => '<div>Hello {{recipient_name}}</div>',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'type', 'html_content', 'is_locked'],
                ],
                'meta' => ['limit', 'offset', 'total', 'has_more'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_list_templates_by_type(): void
    {
        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
        ]);

        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Email',
            'type' => 'email',
            'html_content' => '<div>Hello {{recipient_name}}</div>',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/templates?type=certificate');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_templates_with_search(): void
    {
        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'SPARK Certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
        ]);

        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'General Certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
        ]);

        $response = $this->actingAsJwt()->getJson('/api/v1/templates?search=SPARK');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_create_template(): void
    {
        $payload = [
            'name' => 'New Certificate',
            'description' => 'A test certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
            'css_content' => 'body { width: 1123px; }',
        ];

        $response = $this->actingAsJwt()->postJson('/api/v1/templates', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'type', 'html_content', 'css_content'],
            ])
            ->assertJsonPath('data.name', 'New Certificate')
            ->assertJsonPath('data.type', 'certificate');
    }

    public function test_create_template_validates_required_fields(): void
    {
        $response = $this->actingAsJwt()->postJson('/api/v1/templates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'html_content']);
    }

    public function test_create_template_validates_type(): void
    {
        $payload = [
            'name' => 'Test',
            'type' => 'invalid',
            'html_content' => '<div>test</div>',
        ];

        $response = $this->actingAsJwt()->postJson('/api/v1/templates', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_template_enforces_unique_name(): void
    {
        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Existing Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        $payload = [
            'name' => 'Existing Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ];

        $response = $this->actingAsJwt()->postJson('/api/v1/templates', $payload);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Template name already exists for this organization.');
    }

    public function test_get_template(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Certificate',
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.name', 'Test Certificate');
    }

    public function test_get_template_not_found(): void
    {
        $response = $this->actingAsJwt()->getJson('/api/v1/templates/nonexistent-id');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Template not found.');
    }

    public function test_update_template(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Original Name',
            'type' => 'certificate',
            'html_content' => '<div>original</div>',
        ]);

        $payload = [
            'name' => 'Updated Name',
            'html_content' => '<div>updated</div>',
        ];

        $response = $this->actingAsJwt()->patchJson("/api/v1/templates/{$template->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.html_content', '<div>updated</div>');
    }

    public function test_update_template_not_found(): void
    {
        $response = $this->actingAsJwt()->patchJson('/api/v1/templates/nonexistent-id', [
            'name' => 'Updated',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Template not found.');
    }

    public function test_update_template_validates_unique_name(): void
    {
        CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template A',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        $templateB = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template B',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        $response = $this->actingAsJwt()->patchJson("/api/v1/templates/{$templateB->id}", [
            'name' => 'Template A',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Template name already exists for this organization.');
    }

    public function test_update_locked_template_returns_409(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Locked Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'draft',
        ]);

        $response = $this->actingAsJwt()->patchJson("/api/v1/templates/{$template->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Template is locked and cannot be updated.');
    }

    public function test_delete_template(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Deletable Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        $response = $this->actingAsJwt()->deleteJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('certificate_templates', ['id' => $template->id]);
    }

    public function test_delete_template_not_found(): void
    {
        $response = $this->actingAsJwt()->deleteJson('/api/v1/templates/nonexistent-id');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Template not found.');
    }

    public function test_delete_template_referenced_by_event_returns_409(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Event Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'draft',
        ]);

        $response = $this->actingAsJwt()->deleteJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Template is referenced by events. Use force=true to delete.');
    }

    public function test_delete_template_referenced_by_certificate_returns_409(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Certificate Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        Certificate::create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
            'recipient_name' => 'Test User',
            'recipient_email' => 'test@example.com',
            'certificate_number' => 'CERT-0001',
        ]);

        $response = $this->actingAsJwt()->deleteJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Template is referenced by issued certificates and cannot be deleted.');
    }

    public function test_template_is_locked_flag(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Locked Template',
            'type' => 'certificate',
            'html_content' => '<div>test</div>',
        ]);

        Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'draft',
        ]);

        $response = $this->actingAsJwt()->getJson("/api/v1/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.locked_reason', 'Referenced by event Test Event');
    }
}
