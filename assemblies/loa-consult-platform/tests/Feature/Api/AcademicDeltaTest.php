<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicDeltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_converged_to_course_link(): void
    {
        $this->assertTrue(Schema::hasColumn('sections', 'department_course_id'));
        $this->assertTrue(Schema::hasColumn('sections', 'program'));
        $this->assertFalse(Schema::hasColumn('sections', 'subject_id'));
        $this->assertFalse(Schema::hasColumn('sections', 'schedule'));
        $this->assertFalse(Schema::hasColumn('sections', 'room'));
    }

    public function test_faculty_subjects_gained_semester_link(): void
    {
        $this->assertTrue(Schema::hasColumn('faculty_subjects', 'semester_id'));
        $this->assertTrue(Schema::hasColumn('faculty_subjects', 'faculty_id'));
        $this->assertTrue(Schema::hasColumn('faculty_subjects', 'subject_id'));
    }

    public function test_enrollments_converged_to_section_links(): void
    {
        $this->assertTrue(Schema::hasColumn('student_enrollments', 'section_id'));
        $this->assertTrue(Schema::hasColumn('student_enrollments', 'semester_id'));
        $this->assertTrue(Schema::hasColumn('student_enrollments', 'faculty_subject_id'));
        $this->assertFalse(Schema::hasColumn('student_enrollments', 'subject_id'));
    }
}
