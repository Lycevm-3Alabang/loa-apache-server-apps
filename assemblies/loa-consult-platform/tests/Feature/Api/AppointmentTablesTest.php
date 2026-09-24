<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_shape(): void
    {
        foreach (['student_id', 'faculty_id', 'session_group_id', 'created_by_email',
            'meeting_type', 'date', 'start_time', 'end_time', 'title', 'description',
            'status', 'action_taken', 'additional_remarks', 'teams_link',
            'teams_sync_status', 'teams_sync_retries', 'teams_sync_error',
            'teams_sync_last_attempt', 'requested_at', 'is_active'] as $col) {
            $this->assertTrue(Schema::hasColumn('appointments', $col), "missing appointments.$col");
        }
    }

    public function test_slots_attendees_files_shape(): void
    {
        foreach (['appointment_id', 'date', 'start_time', 'end_time', 'teams_link'] as $col) {
            $this->assertTrue(Schema::hasColumn('appointment_time_slots', $col), "missing slots.$col");
        }
        foreach (['appointment_id', 'user_id', 'status', 'is_mandatory'] as $col) {
            $this->assertTrue(Schema::hasColumn('appointment_attendees', $col), "missing attendees.$col");
        }
        foreach (['appointment_id', 'file_name', 'file_type', 'file_data', 'file_size'] as $col) {
            $this->assertTrue(Schema::hasColumn('appointment_files', $col), "missing files.$col");
        }
    }

    public function test_availability_rules_shape(): void
    {
        foreach (['faculty_id', 'day_of_week', 'is_blocked', 'start_time',
            'end_time', 'start_date', 'end_date', 'is_active'] as $col) {
            $this->assertTrue(Schema::hasColumn('faculty_availability_rules', $col), "missing rules.$col");
        }
    }
}
