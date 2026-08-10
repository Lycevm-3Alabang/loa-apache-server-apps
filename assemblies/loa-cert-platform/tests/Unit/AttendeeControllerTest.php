<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendeeControllerTest extends TestCase
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

    public function test_attendee_index_returns_collection()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $response = $this->get("/api/v1/events/{$event->id}/attendees");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta'
        ]);
    }

    public function test_attendee_store_creates_new_attendee()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $attendeeData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $response = $this->post("/api/v1/events/{$event->id}/attendees", $attendeeData);
        
        $response->assertStatus(201);
        $response->assertJsonStructure(['data']);
        
        $this->assertDatabaseHas('event_attendees', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_attendee_update_updates_attendee()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $attendee = EventAttendee::factory()->create([
            'event_id' => $event->id,
            'name' => 'Original Name',
            'email' => 'john@example.com'
        ]);

        $updateData = [
            'name' => 'Updated Name'
        ];

        $response = $this->patch("/api/v1/attendees/{$attendee->id}", $updateData);
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_attendee_destroy_deletes_attendee()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $attendee = EventAttendee::factory()->create([
            'event_id' => $event->id,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $response = $this->delete("/api/v1/attendees/{$attendee->id}");
        
        $response->assertStatus(204);
        $this->assertDatabaseMissing('event_attendees', [
            'id' => $attendee->id
        ]);
    }

    public function test_attendee_import_processes_bulk_data()
    {
        $event = Event::factory()->create([
            'name' => 'Test Event',
            'certificate_number_pattern' => 'CERT-####',
        ]);

        $importData = [
            'attendees' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com']
            ]
        ];

        $response = $this->post("/api/v1/events/{$event->id}/attendees/import", $importData);
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}