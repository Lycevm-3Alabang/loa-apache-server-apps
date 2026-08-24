<?php

namespace Tests\Feature\Api;

use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class TemplateVisibilityTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private const OWNER_SUB = '00000000-0000-0000-0000-000000000aa1';
    private const OTHER_SUB = '00000000-0000-0000-0000-000000000bb2';
    private const ADMIN_SUB = '00000000-0000-0000-0000-000000000cc3';

    private Organization $organization;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Lyceum of Alabang',
            'slug' => 'loa',
        ]);

        config(['cert-platform.organization_id' => $this->organization->id]);

        $this->event = Event::create([
            'organization_id' => $this->organization->id,
            'name' => 'Visibility Event',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeTemplate(array $attributes = []): CertificateTemplate
    {
        return CertificateTemplate::create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => uniqid('TPL_'),
            'type' => 'certificate',
            'html_content' => '<div>{{recipient_name}}</div>',
            'visibility' => CertificateTemplate::VISIBILITY_PUBLIC,
            'created_by' => self::OWNER_SUB,
            'updated_by' => self::OWNER_SUB,
        ], $attributes));
    }

    private function actAs(string $sub, array $groups = []): self
    {
        return $this->withHeader(
            'Authorization',
            'Bearer ' . $this->createJwtToken(['sub' => $sub, 'groups' => $groups]),
        );
    }

    private function actAsOwner(): self
    {
        return $this->actAs(self::OWNER_SUB);
    }

    private function actAsOther(): self
    {
        return $this->actAs(self::OTHER_SUB);
    }

    private function actAsAdmin(): self
    {
        return $this->actAs(self::ADMIN_SUB, ['cert-admin']);
    }

    // ─── List (index) ────────────────────────────────────────────────────────

    public function test_public_template_is_listed_to_other_users(): void
    {
        $template = $this->makeTemplate();

        $response = $this->actAsOther()->getJson('/api/v1/templates');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $template->id, 'visibility' => 'public']);
    }

    public function test_private_template_is_hidden_from_list_for_non_owners(): void
    {
        $public = $this->makeTemplate();
        $private = $this->makeTemplate([
            'name' => 'Secret Template',
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $response = $this->actAsOther()->getJson('/api/v1/templates');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $public->id]);
        $response->assertJsonMissing(['id' => $private->id]);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_owner_sees_own_private_template_in_list(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $response = $this->actAsOwner()->getJson('/api/v1/templates');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $private->id]);
    }

    public function test_cert_admin_sees_private_templates_in_list(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $response = $this->actAsAdmin()->getJson('/api/v1/templates');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $private->id]);
    }

    // ─── Show ────────────────────────────────────────────────────────────────

    public function test_show_public_template_ok_for_any_user(): void
    {
        $template = $this->makeTemplate();

        $this->actAsOther()->getJson("/api/v1/templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.visibility', 'public');
    }

    public function test_show_private_template_returns_404_for_non_owner(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsOther()->getJson("/api/v1/templates/{$private->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Template not found.');
    }

    public function test_show_private_template_ok_for_owner(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsOwner()->getJson("/api/v1/templates/{$private->id}")
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.created_by', self::OWNER_SUB);
    }

    public function test_show_private_template_ok_for_admin(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsAdmin()->getJson("/api/v1/templates/{$private->id}")->assertOk();
    }

    // ─── Create ──────────────────────────────────────────────────────────────

    public function test_store_defaults_visibility_to_private_and_stamps_owner(): void
    {
        $response = $this->actAsOther()->postJson('/api/v1/templates', [
            'name' => 'Default Visibility',
            'type' => 'certificate',
            'html_content' => '<x></x>',
        ]);

        $response->assertCreated();

        $template = CertificateTemplate::where('name', 'Default Visibility')->firstOrFail();

        $this->assertEquals(CertificateTemplate::VISIBILITY_PRIVATE, $template->visibility);
        $this->assertEquals(self::OTHER_SUB, $template->created_by);
        $this->assertEquals(self::OTHER_SUB, $template->updated_by);
    }

    public function test_store_honors_explicit_public_visibility(): void
    {
        $response = $this->actAsOther()->postJson('/api/v1/templates', [
            'name' => 'Shared Template',
            'type' => 'certificate',
            'html_content' => '<x></x>',
            'visibility' => 'public',
        ]);

        $response->assertCreated();
        $this->assertEquals(CertificateTemplate::VISIBILITY_PUBLIC, $response->json('data.visibility'));
    }

    public function test_store_rejects_invalid_visibility(): void
    {
        $this->actAsOther()->postJson('/api/v1/templates', [
            'name' => 'Bad Visibility',
            'type' => 'certificate',
            'html_content' => '<x></x>',
            'visibility' => 'internal',
        ])->assertStatus(422);
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function test_owner_can_toggle_visibility(): void
    {
        $template = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsOwner()->patchJson("/api/v1/templates/{$template->id}", [
            'visibility' => 'public',
        ])->assertOk()->assertJsonPath('data.visibility', 'public');
    }

    public function test_admin_can_toggle_someone_elses_visibility(): void
    {
        $template = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsAdmin()->patchJson("/api/v1/templates/{$template->id}", [
            'visibility' => 'public',
        ])->assertOk()->assertJsonPath('data.visibility', 'public');
    }

    public function test_non_owner_cannot_toggle_visibility_even_on_visible_templates(): void
    {
        $template = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PUBLIC,
        ]);

        $response = $this->actAsOther()->patchJson("/api/v1/templates/{$template->id}", [
            'visibility' => 'private',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(CertificateTemplate::VISIBILITY_PUBLIC, $template->fresh()->visibility);
    }

    public function test_successful_update_restamps_updated_by(): void
    {
        $template = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        // Admin edits someone else's private template — becomes a co-owner (§5.3).
        $this->actAsAdmin()->patchJson("/api/v1/templates/{$template->id}", [
            'description' => 'Touched by admin',
        ])->assertOk();

        $fresh = $template->fresh();

        $this->assertEquals(self::ADMIN_SUB, $fresh->updated_by);
        $this->assertEquals(self::OWNER_SUB, $fresh->created_by);

        // Admin is now in the owner set → retains visibility.
        $this->assertTrue($fresh->isOwnedBy(self::ADMIN_SUB));
        $this->actAsAdmin()->getJson("/api/v1/templates/{$fresh->id}")->assertOk();
    }

    // ─── Clone endpoints (side doors) ────────────────────────────────────────

    public function test_clone_certificate_private_source_returns_404_for_non_owner(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsOther()->postJson("/api/v1/events/{$this->event->id}/clone-template", [
            'source_template_id' => $private->id,
            'name' => 'Sneaky Clone',
        ])->assertNotFound();
    }

    public function test_clone_certificate_public_source_creates_private_clone_owned_by_cloner(): void
    {
        $source = $this->makeTemplate();

        $response = $this->actAsOther()->postJson("/api/v1/events/{$this->event->id}/clone-template", [
            'source_template_id' => $source->id,
            'name' => 'My Clone',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'My Clone');

        $clone = CertificateTemplate::findOrFail($response->json('data.template_id'));

        $this->assertEquals(CertificateTemplate::VISIBILITY_PRIVATE, $clone->visibility);
        $this->assertEquals(self::OTHER_SUB, $clone->created_by);
        $this->assertEquals(self::OTHER_SUB, $clone->updated_by);
        $this->assertEquals($clone->id, $this->event->fresh()->template_id);
    }

    public function test_clone_email_private_source_returns_404_for_non_owner(): void
    {
        $private = $this->makeTemplate([
            'type' => 'email',
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $this->actAsOther()->postJson("/api/v1/events/{$this->event->id}/clone-email-template", [
            'source_template_id' => $private->id,
            'name' => 'Sneaky Email Clone',
        ])->assertNotFound();
    }

    public function test_clone_email_public_source_ok_for_other_user(): void
    {
        $source = $this->makeTemplate(['type' => 'email']);

        $this->actAsOther()->postJson("/api/v1/events/{$this->event->id}/clone-email-template", [
            'source_template_id' => $source->id,
            'name' => 'Email Clone',
        ])->assertOk();
    }

    // ─── Event references ────────────────────────────────────────────────────

    public function test_event_store_rejects_private_template_reference_for_non_owner(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $response = $this->actAsOther()->postJson('/api/v1/events', [
            'name' => 'Ref Event',
            'certificate_number_pattern' => 'REF-####',
            'template_id' => $private->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['template_id']);
    }

    public function test_event_store_accepts_public_template_reference(): void
    {
        $public = $this->makeTemplate();

        $response = $this->actAsOther()->postJson('/api/v1/events', [
            'name' => 'Ref Event Public',
            'certificate_number_pattern' => 'REFP-####',
            'template_id' => $public->id,
        ]);

        $response->assertCreated();
        $this->assertEquals($public->id, $response->json('data.template_id'));
    }

    public function test_event_update_rejects_newly_private_template_reference(): void
    {
        $private = $this->makeTemplate([
            'visibility' => CertificateTemplate::VISIBILITY_PRIVATE,
        ]);

        $response = $this->actAsOther()->patchJson("/api/v1/events/{$this->event->id}", [
            'template_id' => $private->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['template_id']);
    }

    public function test_existing_event_reference_is_grandfathered_after_privatization(): void
    {
        $template = $this->makeTemplate();

        $event = Event::create([
            'organization_id' => $this->organization->id,
            'template_id' => $template->id,
            'name' => 'Grandfathered',
            'certificate_number_pattern' => 'GRAND-####',
            'status' => 'active',
        ]);

        // Author privatizes the template after the event referenced it.
        $template->update(['visibility' => CertificateTemplate::VISIBILITY_PRIVATE]);

        // Unrelated field patch on the event still succeeds — reference is grandfathered.
        $this->actAsOwner()->patchJson("/api/v1/events/{$event->id}", [
            'name' => 'Grandfathered Renamed',
        ])->assertOk();
    }
}
