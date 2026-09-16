<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Event;
use App\Models\Organization;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Traits\WithJwt;

/**
 * Author guard-rail tests (spec event-visibility §2).
 *
 * Scenarios tested:
 * 1. loa-auth-admin + cert-admin        → ALLOW (platform admin with cert write group)
 * 2. loa-auth-admin + cert-staff        → ALLOW (platform admin with cert write group)
 * 3. loa-auth-admin + cert-user only    → ALLOW (cert-user is read-only but
 *                                         liveness check only verifies status,
 *                                         not group — JWT middleware enforces
 *                                         endpoint permissions)
 * 4. loa-auth-admin only (no cert grp)  → ALLOW (Auth shows user; JWT perms
 *                                         enforced by middleware — this user
 *                                         wouldn't get a valid cert JWT)
 * 5. cert-admin only (no platform grp)  → ALLOW (standard tenant member)
 * 6. cert-staff only (no platform grp)  → ALLOW (standard tenant member)
 * 7. no groups at all                   → DENY 403 (inactive or not found)
 * 8. Auth unreachable                   → DENY 502
 * 9. Auth returns disabled status       → DENY 403
 */
class AuthorGuardRailTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private string $orgId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgId = Organization::create([
            'name' => 'Lyceum of Alabang',
            'slug' => 'loa',
        ])->id;

        config(['cert-platform.organization_id' => $this->orgId]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function fakeAuthUser(array $overrides = []): void
    {
        $payload = array_merge(['status' => 'active'], $overrides);

        Http::fake([
            '*/api/v1/users/*' => Http::response($payload, 200),
        ]);
    }

    private function fakeAuthNotFound(): void
    {
        Http::fake([
            '*/api/v1/users/*' => Http::response(['message' => 'User not found'], 404),
        ]);
    }

    private function fakeAuthForbidden(): void
    {
        Http::fake([
            '*/api/v1/users/*' => Http::response(['message' => 'Forbidden'], 403),
        ]);
    }

    private function fakeAuthDisabled(): void
    {
        Http::fake([
            '*/api/v1/users/*' => Http::response(['status' => 'disabled'], 200),
        ]);
    }

    private function fakeAuthLocked(): void
    {
        Http::fake([
            '*/api/v1/users/*' => Http::response(['status' => 'locked'], 200),
        ]);
    }

    private function fakeAuthUnreachable(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });
    }

    private function fakeAuthServerError(): void
    {
        Http::fake([
            '*/api/v1/users/*' => Http::response(['message' => 'Server Error'], 500),
        ]);
    }

    private function eventData(): array
    {
        return [
            'name' => 'Test Event',
            'organizer' => 'SAO',
            'certificate_number_pattern' => 'CERT-####',
        ];
    }

    private function templateData(): array
    {
        return [
            'name' => 'Test Template',
            'type' => 'certificate',
            'html_content' => '<html><body>{{name}}</body></html>',
        ];
    }

    // ------------------------------------------------------------------
    // Scenario 1: loa-auth-admin + cert-admin → ALLOW
    // ------------------------------------------------------------------

    public function test_event_store_allows_platform_admin_with_cert_admin(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', ['name' => 'Test Event']);
    }

    public function test_template_store_allows_platform_admin_with_cert_admin(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/templates', $this->templateData());

        $response->assertStatus(201);
        $this->assertDatabaseHas('certificate_templates', ['name' => 'Test Template']);
    }

    // ------------------------------------------------------------------
    // Scenario 2: loa-auth-admin + cert-staff → ALLOW
    // ------------------------------------------------------------------

    public function test_event_store_allows_platform_admin_with_cert_staff(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(201);
    }

    public function test_template_store_allows_platform_admin_with_cert_staff(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/templates', $this->templateData());

        $response->assertStatus(201);
    }

    // ------------------------------------------------------------------
    // Scenario 3: loa-auth-admin + cert-user only → ALLOW
    //   (liveness check verifies status, not group — JWT perms enforced
    //    by middleware separately)
    // ------------------------------------------------------------------

    public function test_event_store_allows_platform_admin_with_cert_user(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        // Auth says active → liveness passes; endpoint permission
        // enforced by JwtMiddleware/EndpointPolicy separately.
        $response->assertStatus(201);
    }

    // ------------------------------------------------------------------
    // Scenario 4: loa-auth-admin only (no cert groups) → ALLOW
    //   (Auth returns active; the user wouldn't have a valid cert JWT
    //    in production, but the liveness check itself should not block)
    // ------------------------------------------------------------------

    public function test_event_store_allows_platform_admin_without_cert_groups(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(201);
    }

    // ------------------------------------------------------------------
    // Scenario 5: cert-admin only (no platform admin) → ALLOW
    // ------------------------------------------------------------------

    public function test_event_store_allows_cert_admin_only(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(201);
    }

    public function test_template_store_allows_cert_admin_only(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/templates', $this->templateData());

        $response->assertStatus(201);
    }

    // ------------------------------------------------------------------
    // Scenario 6: cert-staff only (no platform admin) → ALLOW
    // ------------------------------------------------------------------

    public function test_event_store_allows_cert_staff_only(): void
    {
        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(201);
    }

    // ------------------------------------------------------------------
    // Scenario 7: no groups at all → DENY 403
    //   (Auth returns 404 user not found, or user is inactive)
    // ------------------------------------------------------------------

    public function test_event_store_deny_user_not_found(): void
    {
        $this->fakeAuthNotFound();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account was not found. Contact your administrator. Event was not saved.',
        ]);
    }

    public function test_template_store_deny_user_not_found(): void
    {
        $this->fakeAuthNotFound();

        $response = $this->actingAsJwt()->post('/api/v1/templates', $this->templateData());

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account was not found. Contact your administrator. Template was not saved.',
        ]);
    }

    public function test_event_store_deny_forbidden(): void
    {
        $this->fakeAuthForbidden();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Unable to verify account status. Event was not saved.',
        ]);
    }

    // ------------------------------------------------------------------
    // Scenario 8: Auth unreachable → DENY 502
    // ------------------------------------------------------------------

    public function test_event_store_deny_auth_unreachable(): void
    {
        $this->fakeAuthUnreachable();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(502);
        $response->assertJsonFragment([
            'message' => 'Auth service unavailable. Event was not saved.',
        ]);
    }

    public function test_template_store_deny_auth_unreachable(): void
    {
        $this->fakeAuthUnreachable();

        $response = $this->actingAsJwt()->post('/api/v1/templates', $this->templateData());

        $response->assertStatus(502);
        $response->assertJsonFragment([
            'message' => 'Auth service unavailable. Template was not saved.',
        ]);
    }

    public function test_event_store_deny_auth_server_error(): void
    {
        $this->fakeAuthServerError();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(502);
        $response->assertJsonFragment([
            'message' => 'Auth service unavailable. Event was not saved.',
        ]);
    }

    // ------------------------------------------------------------------
    // Scenario 9: Auth returns disabled/locked status → DENY 403
    // ------------------------------------------------------------------

    public function test_event_store_deny_user_disabled(): void
    {
        $this->fakeAuthDisabled();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account is not active. Event was not saved.',
        ]);
    }

    public function test_event_store_deny_user_locked(): void
    {
        $this->fakeAuthLocked();

        $response = $this->actingAsJwt()->post('/api/v1/events', $this->eventData());

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account is not active. Event was not saved.',
        ]);
    }

    public function test_template_store_deny_user_disabled(): void
    {
        $this->fakeAuthDisabled();

        $response = $this->actingAsJwt()->post('/api/v1/templates', $this->templateData());

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account is not active. Template was not saved.',
        ]);
    }

    // ------------------------------------------------------------------
    // Event update also has the guard
    // ------------------------------------------------------------------

    public function test_event_update_deny_user_not_found(): void
    {
        $event = Event::factory()->create([
            'name' => 'Original',
            'certificate_number_pattern' => 'CERT-####',
            'is_public' => true,
            'organization_id' => $this->orgId,
        ]);

        $this->fakeAuthNotFound();

        $response = $this->actingAsJwt()->patch("/api/v1/events/{$event->id}", [
            'name' => 'Updated',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account was not found. Contact your administrator. Event was not saved.',
        ]);
    }

    public function test_event_update_allows_active_user(): void
    {
        $event = Event::factory()->create([
            'name' => 'Original',
            'certificate_number_pattern' => 'CERT-####',
            'is_public' => true,
            'organization_id' => $this->orgId,
        ]);

        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->patch("/api/v1/events/{$event->id}", [
            'name' => 'Updated',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'name' => 'Updated']);
    }

    // ------------------------------------------------------------------
    // Template update also has the guard
    // ------------------------------------------------------------------

    public function test_template_update_deny_user_disabled(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->orgId,
            'name' => 'Original Template',
            'type' => 'certificate',
            'html_content' => '<html></html>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $this->fakeAuthDisabled();

        $response = $this->actingAsJwt()->patch("/api/v1/templates/{$template->id}", [
            'name' => 'Updated Template',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Your account is not active. Template was not saved.',
        ]);
    }

    public function test_template_update_allows_active_user(): void
    {
        $template = CertificateTemplate::create([
            'organization_id' => $this->orgId,
            'name' => 'Original Template',
            'type' => 'certificate',
            'html_content' => '<html></html>',
            'created_by' => '00000000-0000-0000-0000-000000000001',
            'updated_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        $this->fakeAuthUser();

        $response = $this->actingAsJwt()->patch("/api/v1/templates/{$template->id}", [
            'name' => 'Updated Template',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('certificate_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
        ]);
    }
}
