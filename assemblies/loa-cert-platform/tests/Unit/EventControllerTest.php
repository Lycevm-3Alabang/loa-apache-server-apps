<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cert-platform.organization_id' => Organization::create([
            'name' => 'Lyceum of Alabang',
            'slug' => 'loa',
        ])->id]);
    }

    public function test_event_index_returns_collection()
    {
        $response = $this->get('/api/v1/events');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta'
        ]);
    }

    public function test_event_store_creates_new_event()
    {
        $eventData = [
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ];

        $response = $this->post('/api/v1/events', $eventData);
        
        $response->assertStatus(201);
        $response->assertJsonStructure(['data']);
        
        $this->assertDatabaseHas('events', [
            'name' => 'Test Event',
        ]);
    }

    public function test_event_show_returns_single_event()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $response = $this->get("/api/v1/events/{$event->id}");
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $response->assertJsonFragment(['name' => 'Test Event']);
    }

    public function test_event_update_updates_event()
    {
        $event = Event::factory()->create([
            'name' => 'Original Name',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $updateData = [
            'name' => 'Updated Name'
        ];

        $response = $this->patch("/api/v1/events/{$event->id}", $updateData);
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_event_destroy_deletes_event()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $response = $this->delete("/api/v1/events/{$event->id}");
        
        $response->assertStatus(204);
        $this->assertDatabaseMissing('events', [
            'id' => $event->id
        ]);
    }
}