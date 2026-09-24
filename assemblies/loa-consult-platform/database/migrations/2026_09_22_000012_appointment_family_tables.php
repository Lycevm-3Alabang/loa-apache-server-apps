<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2 appointment-family tables (data-model.md Final v1.3 §3.2).
 *
 * Table names follow the §3 table headers (the §7 order list abbreviates).
 * Notes:
 * - students/employees PKs are UUID → foreignUuid links.
 * - UNIQUE names that exceed MySQL's 64-char limit use explicit shorts
 *   (repo precedent: auth up_scope_unique; B1 enr_stu_map_sem_unique).
 * - date columns are DATE, clock times VARCHAR (semesters DATE precedent);
 *   file_data is MEDIUMTEXT (5 MB base64 images per module spec).
 * - CASCADE only where §3.2 states it; otherwise plain constrained().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('student_id')->nullable()->constrained('students');
            $table->foreignUuid('faculty_id')->constrained('employees');
            $table->string('session_group_id')->nullable();
            $table->string('created_by_email');
            $table->string('meeting_type')->default('CONSULTATION');
            $table->date('date')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('PENDING');
            $table->text('action_taken')->nullable();
            $table->text('additional_remarks')->nullable();
            $table->string('teams_link')->nullable();
            $table->string('teams_sync_status')->default('UNWRITTEN');
            $table->integer('teams_sync_retries')->default(0);
            $table->text('teams_sync_error')->nullable();
            $table->timestamp('teams_sync_last_attempt')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('appointment_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('teams_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(['appointment_id', 'date', 'start_time'], 'appt_slot_unique');
        });

        Schema::create('appointment_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status')->default('INVITED');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(['appointment_id', 'user_id']);
        });

        Schema::create('appointment_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type');
            $table->mediumText('file_data');
            $table->integer('file_size');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('faculty_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('faculty_id')->constrained('employees');
            $table->integer('day_of_week');
            $table->boolean('is_blocked')->default(false);
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(['faculty_id', 'day_of_week', 'start_date'], 'avail_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_availability_rules');
        Schema::dropIfExists('appointment_files');
        Schema::dropIfExists('appointment_attendees');
        Schema::dropIfExists('appointment_time_slots');
        Schema::dropIfExists('appointments');
    }
};
