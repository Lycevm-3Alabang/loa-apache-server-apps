<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithJwt;

class EventVisibilityTest extends TestCase
{
    use RefreshDatabase, WithJwt;

    private const OWNER_SUB = '00000000-0000-0000-0000-000000000aa1';
    private const OTHER_SUB = '00000000-0000-0000-0000-000000000bb2';
    private const ADMIN_SUB = '00000000-0000-0000-0000-000000000cc3';
    private const DISABLED_SUB = '00000000-0000-0000-0000-000000000dd4';

    private Organization $organization;
    private array $authorResponse = ['status' => 'active'];
    private int $authorResponseStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Lyceum of Alabang',
            'slug' => 'loa',
        ]);

        config(['cert-platform.organization_id' => $this->organization->id]);

        $this->fakeActiveAuthor();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function fakeActiveAuthor(): void
    {
        Http::fake(fn () => Http::response($this->authorResponse, $this->authorResponseStatus));
    }

    private function fakeAuthorResponse(array $body, int $status): void
    {
        $this->authorResponse = $body;
        $this->authorResponseStatus = $status;
    }

    private function makeEvent(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => uniqid('EVT_'),
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
            'is_public' => false,
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Visibility Event',
            'organizer' => 'SAO',
            'certificate_number_pattern' => 'CERT-####',
            'status' => 'active',
        ], $overrides);
    }

    // ─── Authorship stamping ─────────────────────────────────────────────────

    public function test_store_stamps_author_and_defaults_private(): void
    {
        $response = $this->actAs(self::OWNER_SUB)->postJson('/api/v1/events', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.created_by', self::OWNER_SUB);

        $this->assertDatabaseHas('events', [
            'id' => $response->json('data.id'),
            'created_by' => self::OWNER_SUB,
            'updated_by' => self::OWNER_SUB,
        ]);
    }

    public function test_store_ignores_client_authorship_fields(): void
    {
        $response = $this->actAs(self::OWNER_SUB)->postJson('/api/v1/events', $this->validPayload([
            'created_by' => self::OTHER_SUB,
            'updated_by' => self::OTHER_SUB,
            'is_public' => true,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.created_by', self::OWNER_SUB);

        $this->assertDatabaseHas('events', [
            'id' => $response->json('data.id'),
            'created_by' => self::OWNER_SUB,
            'updated_by' => self::OWNER_SUB,
            'is_public' => true,
        ]);
    }

    public function test_update_restamps_updated_by_and_ignores_created_by(): void
    {
        $event = $this->makeEvent(['updated_by' => self::OTHER_SUB]);

        $response = $this->actAs(self::OWNER_SUB)->patchJson("/api/v1/events/{$event->id}", [
            'name' => 'Renamed',
            'created_by' => self::OTHER_SUB,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Renamed',
            'created_by' => self::OWNER_SUB,
            'updated_by' => self::OWNER_SUB,
        ]);
    }

    // ─── Visibility ──────────────────────────────────────────────────────────

    public function test_private_event_hidden_from_other_staff_list_and_show(): void
    {
        $public = $this->makeEvent(['name' => 'Public Event', 'is_public' => true]);
        $private = $this->makeEvent(['name' => 'Private Event']);

        $response = $this->actAs(self::OTHER_SUB)->getJson('/api/v1/events');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $public->id]);
        $response->assertJsonMissing(['id' => $private->id]);
        $this->assertEquals(1, $response->json('meta.total'));

        $this->actAs(self::OTHER_SUB)->getJson("/api/v1/events/{$private->id}")->assertNotFound();
    }

    public function test_owner_and_admin_see_private_event(): void
    {
        $private = $this->makeEvent();

        $this->actAs(self::OWNER_SUB)->getJson("/api/v1/events/{$private->id}")->assertOk();
        $this->actAs(self::ADMIN_SUB, ['cert-admin'])->getJson("/api/v1/events/{$private->id}")->assertOk();
        $this->actAs(self::ADMIN_SUB, ['cert-admin'])->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonFragment(['id' => $private->id]);
    }

    public function test_update_private_event_by_non_owner_returns_404(): void
    {
        $private = $this->makeEvent();

        $this->actAs(self::OTHER_SUB)->patchJson("/api/v1/events/{$private->id}", [
            'name' => 'Sneaky Rename',
        ])->assertNotFound();
    }

    public function test_visibility_flip_requires_owner_or_admin(): void
    {
        $private = $this->makeEvent();

        // Non-owner flip attempt on a VISIBLE event (public) is forbidden...
        $public = $this->makeEvent(['is_public' => true]);
        $this->actAs(self::OTHER_SUB)->patchJson("/api/v1/events/{$public->id}", [
            'is_public' => false,
        ])->assertForbidden();

        // ...while the owner flip succeeds.
        $this->actAs(self::OWNER_SUB)->patchJson("/api/v1/events/{$private->id}", [
            'is_public' => true,
        ])->assertOk()
            ->assertJsonPath('data.is_public', true);
    }

    // ─── Author guard rail ───────────────────────────────────────────────────

    public function test_store_rejected_for_disabled_author(): void
    {
        $this->fakeAuthorResponse(['status' => 'disabled'], 200);

        $this->actAs(self::DISABLED_SUB)->postJson('/api/v1/events', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('events', ['name' => 'Visibility Event']);
    }

    public function test_store_rejected_for_unknown_author(): void
    {
        $this->fakeAuthorResponse(['message' => 'User not found'], 404);

        $this->actAs(self::DISABLED_SUB)->postJson('/api/v1/events', $this->validPayload())
            ->assertForbidden();
    }

    public function test_store_returns_502_when_auth_unreachable(): void
    {
        Http::fake(fn () => Http::failedConnection());

        $this->actAs(self::OWNER_SUB)->postJson('/api/v1/events', $this->validPayload())
            ->assertStatus(502);

        $this->assertDatabaseMissing('events', ['name' => 'Visibility Event']);
    }

    public function test_update_rejected_for_disabled_author(): void
    {
        $event = $this->makeEvent(['is_public' => true]);

        $this->fakeAuthorResponse(['status' => 'disabled'], 200);

        // Owner's own public event: visibility passes, guard denies.
        $response = $this->actAs(self::OWNER_SUB)->patchJson("/api/v1/events/{$event->id}", [
            'name' => 'Blocked Rename',
        ]);

        // The guard runs before visibility, so it should return 403.
        $response->assertForbidden();

        // The event should NOT be updated because the request was rejected.
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => $event->name,
        ]);
    }
}
