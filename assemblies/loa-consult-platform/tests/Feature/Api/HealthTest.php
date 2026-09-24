<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'service' => 'loa-consult-platform',
        ]);
    }
}
