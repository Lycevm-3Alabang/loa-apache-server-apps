<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class RouteGatingTest extends TestCase
{
    public function test_health_stays_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'service' => 'loa-consult-platform']);
    }

    public function test_count_active_stays_public(): void
    {
        $response = $this->getJson('/api/v1/semesters/count-active');

        // Public route: never 401 for missing token (200 empty or 500
        // without seed data — both prove the gate did not reject).
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_semesters_gated_tokenless(): void
    {
        $this->getJson('/api/v1/semesters')->assertUnauthorized();
    }

    public function test_admin_gated_tokenless(): void
    {
        $this->getJson('/api/v1/departments')->assertUnauthorized();
    }

    public function test_auth_callback_reachable_tokenless(): void
    {
        // Ungated auth route: missing payload → 400 from the controller,
        // proving jwt.auth did not intercept with 401 first.
        $this->postJson('/api/v1/auth/callback', [])
            ->assertStatus(400)
            ->assertJson(['message' => 'Missing payload']);
    }
}
