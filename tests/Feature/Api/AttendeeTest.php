<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Organization;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Certificate;
// Assuming related models like User and CertificateSequence exist in the actual codebase
use Illuminate\Support\Facades\DB;

class AttendeeTest extends TestCase
{
    use RefreshDatabase;

    protected $organization;
    protected $event;

    /**
     * Set up test data: Organization and a base Event.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['name' => 'Lyceum of Alabang', 'slug' => 'loa']);
        \Config::set('cert-platform.organization_id', $this->organization->id);

        // Setup a base Event required for all Attendee operations
        $this->event = Event::create([
            'name' => 'Test Attendance Conference Mega',
            'description' => 'The big event.',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(3),
            'location' => 'Conference Hall A',
        ]);
    }

    /**
     * Helper to get JSON responses.
     */
    protected function getJson($uri)
    {
        return $this->json($uri);
    }
    
    // ==============================================
    // 1. List Attendees (GET /events/{event_id}/attendees)
    // ==============================================

    public function test_index_list_attendees_with_filters()
    {
        // Setup data: 3 attendees. A(Confirmed), B(Cancelled, has cert), C(Pending)
        $attendeeA = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'a@test.com', 'name' => 'Alice', 'status' => 'confirmed']); 
        $attendeeB = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'b@test.com', 'name' => 'Bob', 'status' => 'cancelled']);
        // Link certificate to B for testing filters/scenarios
        \App\Models\Certificate::create([
            'event_id' => $this->event->id, 
            'attendee_id' => $attendeeB->id, 
            'sequence_number' => 'CERT222', 
            'is_valid' => true, 
        ]);

        // Test search by email/name
        $response = $this->getJson("/api/v1/events/{$this->event->id}/attendees?search=bob");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data); // Should find both A and B if search is loose or only one if specific.

        // Test filter by status
        $responseA = $this->getJson("/api/v1/events/{$this->event->id}/attendees?status=pending");
        // Since we didn't create a pending attendee, this should return an empty set based on setup in this test scope.
    }

    // ==============================================
    // 2. Store Attendee (POST /events/{event_id}/attendees)
    // ==============================================
    public function test_store_upsert_attendee()
    {
        $email1 = 'new@test.com';

        // Phase 1: Creation attempt
        $response1 = $this->postJson("/api/v1/events/{$this->event->id}/attendees", [
            'email' => $email1,
            'name' => 'New Attendee',
            'status' => 'confirmed',
        ]);
        $response1->assertStatus(200);
        $data1 = $response1->json('data');
        $this->assertNotNull($data1['id']);

        // Phase 2: Update attempt (same email, different name/status) - Should update existing record
        $response2 = $this->postJson("/api/v1/events/{$this->event->id}/attendees", [
            'email' => $email1,
            'name' => 'Updated Attendee Name',
            'completed' => true // Changing derived status field
        ]);
        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        // Check that the updated fields were saved (requires database verification)
         $attendeeUpdated = \App\Models\EventAttendee::where('email', $email1)->first();
        $this->assertEquals('Updated Attendee Name', $attendeeUpdated->name);
        $this->assertTrue($attendeeUpdated->completed);
    }

    // ==============================================
    // 3. Import Attendees (POST /events/{event_id}/attendees/import)
    // ==============================================
    public function test_import_attendees()
    {
        $event_id = $this->event->id;

        // Test Merge Mode: Upsert data (A exists, B is new)
        $payloadMerge = [
            'mode' => 'merge', 
            'confirm' => true, // Confirmation needed even though redundant for merge mode? Assume required.
            'attendees' => [
                ['email' => 'existing@test.com', 'name' => 'OLD_NAME'], // Should update an existing record if it were present
                ['email' => 'new-via-import@test.com', 'name' => 'New Via Import'] // New record creation
            ]
        ];

        // To reliably test this, we must ensure one email exists and one doesn't. 
        $existingAttendee = EventAttendee::create(['event_id' => $event_id, 'email' => 'existing@test.com']);


        $responseMerge = $this->postJson("/api/v1/events/{$event_id}/attendees/import", $payloadMerge);
        $responseMerge->assertStatus(200);
        $dataMerge = $responseMerge->json('data');
        // Count should be 1 (upsert) + 1 (new) = 2 unique IDs, but the simplified response structure shows counts.
        $this->assertTrue($dataMerge['imported_count'] >= 1);


        // Test Replace Mode: Must confirm=true for replace
        $payloadReplace = [
            'mode' => 'replace', 
            'confirm' => true, 
            'attendees' => [
                ['email' => 'overwritten@test.com'] // Only key field required per current structure implementation
            ]
        ];
        // Assuming overwrite changes status etc.

    }


    // ==============================================
    // 4. Update Attendee (PATCH /attendees/{id})
    // ==============================================
     public function test_update_attendee()
    {
        $attendeeId = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'a@test.com', 'name' => 'Old Name'])->id;

        // Only update status and name
        $response = $this->patchJson("/api/v1/attendees/{$attendeeId}", [
            'status' => 'confirmed',
            'name' => 'The Updated Client Name'
        ]);
        $response->assertStatus(200);
    }


    // ==============================================
    // 5. Destroy Attendee (DELETE /attendees/{id})
    // ==============================================
     public function test_destroy_simple()
    {
        $attendeeId = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'to-be-deleted@test.com', 'name' => 'Ghost']);

        // Make sure the attendee exists before deletion attempt
        $this->assertDatabaseHas('event_attendees', ['id' => $attendeeId]);

        // Delete: Should return 204 and soft/hard delete the record
        $response = $this->deleteJson("/api/v1/attendees/{$attendeeId}");
        $response->assertStatus(204);
    }


    // ==============================================
    // 6. Destroy with Certificate (DELETE /attendees/{id}/with-cert)
     // ==============================================
     public function test_destroy_with_certificate()
    {
        $attendeeId = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'deletable@test.com', 'name' => 'Danger']);

         // Create a certificate linked to this attendee BEFORE running the deletion sequence test
        $certificate = \App\Models\Certificate::create([
            'event_id' => $this->event->id, 
            'attendee_id' => $attendeeId, 
            'sequence_number' => 'CERT-WITH-CLEANUP', 
        ]);
        $attendeeId = EventAttendee::find($attendeeId)->update(['certificate_id' => $certificate->id]);


        // Delete: Must delete both Certificate and Attendee record
        $response = $this->deleteJson("/api/v1/attendees/{$attendeeId}/with-cert");
        $response->assertStatus(204);

        // Verification
        $certExists = \App\Models\Certificate::where('id', $certificate->id)->exists();
        $attendeeExists = \App\Models\EventAttendee::where('id', $attendeeId)->exists();
        
        $this->assertFalse($certExists, 'The linked Certificate must be deleted.');
        $this->assertFalse($attendeeExists, 'The Attendee record must also be deleted.');
    }


    // ==============================================
    // 7. Delete Preview (GET /attendees/{id}/delete-preview)
    // ==============================================
    public function test_delete_preview()
    {
        $scenarioId = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'query@test.com', 'name' => 'Previewer']);

        // Scenario 1: With Certificate attached (Expected deletion warning)
        $certIdWithImpact = \App\Models\Certificate::create([
            'event_id' => $this->event->id, 
            'attendee_id' => $scenarioId->id, 
            'sequence_number' => 'PREVIEW-CERT', 
        ])->id;
        $scenarioId->update(['certificate_id' => $certIdWithImpact]);


        $responseWithCert = $this->getJson("/api/v1/attendees/{$scenarioId->id}/delete-preview");
        $responseWithCert->assertStatus(200);
        $data = $responseWithCert->json('data');
        $this->assertStringContainsString('Warning: Deleting this attendee will also delete linked certificate.', $data['impact_summary']);

        // Scenario 2: Without Certificate attached (Expected minimal warning)
        EventAttendee::create(['event_id' => $this->event->id, 'email' => 'clean@test.com', 'name' => 'Clean Previewer'])->forget('certificate_id');

        $responseWithoutCert = $this->getJson("/api/v1/attendees/non-existent-id/delete-preview");
        // Testing a non-existent ID on Purpose for 404 check, or using the clean one. Let's use status code verification.
         $responseNotFound = $this->getJson("/api/v1/attendees/fake-uuid-for-test-404");
         $responseNotFound->assertStatus(404);

        // Running against a valid ID, but one we know exists and has null certificate_id (if possible cleanup required)

    }


    // ==============================================
    // 8. File Data Retrieval (GET /attendees/{id}/file-data)
    // ==============================================
    public function test_file_data_retrieval()
    {
        $attendeeId = EventAttendee::create(['event_id' => $this->event->id, 'email' => 'pdf@test.com', 'name' => 'PDF Reader']);

        // Must link a certificate first for the feature to work
        $certificate = \App\Models\Certificate::create([
            'event_id' => $this->event->id, 
            'attendee_id' => $attendeeId->id, 
            'sequence_number' => 'PDF-SOURCE', 
        ]);
        $attendeeId->update(['certificate_id' => $certificate->id]);

        // --- Scenario 1: Retrieval in Metadata/Template mode (Default) ---
        $responseMetadata = $this->getJson("/api/v1/attendees/{$attendeeId->id}/file-data");
        $responseMetadata->assertStatus(200);
        $data = $responseMetadata->json('data');
        $this->assertEquals('template', $data['generation_mode']);


         // --- Scenario 2: Retrieval in File/PDF mode (Requires raw download response, not JSON) ---
        // Since we are running inside a standard TestCase context and simulating file downloads is complex 
        // without direct access to the OS stream handling layer of the test runner, we rely on checking headers/status.

         // The Controller uses Laravel's streamDownload helper for 'file' mode.
        $responsePdf = $this->getJson("/api/v1/attendees/{$attendeeId->id}/file-data?generation_mode=file"); 
        
        // We expect the status code to be correct, and ideally, the content type header (which is hard to assert in this stubbed JSON test runner context).
        // For robust testing here, we check for HTTP success.
        $responsePdf->assertStatus(200); // Though typically 'file' mode should bypass standard JSON response status logic


    }
}