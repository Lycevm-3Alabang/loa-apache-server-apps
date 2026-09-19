<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // app_users — add updated_at, created_by, updated_by
        Schema::table('app_users', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // departments — replace is_disabled with is_active, add audit columns
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('is_disabled');
            $table->boolean('is_active')->default(true)->after('dean_id');
            $table->timestamp('created_at')->nullable()->after('is_active');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // department_courses — add audit columns
        Schema::table('department_courses', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('code');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // subjects — add audit columns
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
            $table->timestamp('created_at')->nullable()->after('is_active');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // sections — replace is_disabled with is_active, add audit columns
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('is_disabled');
            $table->boolean('is_active')->default(true)->after('department_course_id');
            $table->timestamp('created_at')->nullable()->after('is_active');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // faculty_subjects — add audit columns
        Schema::table('faculty_subjects', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('section_id');
            $table->timestamp('created_at')->nullable()->after('is_active');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // student_enrollments — add audit columns
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('section_id');
            $table->timestamp('created_at')->nullable()->after('is_active');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // semesters — add audit columns
        Schema::table('semesters', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // students — add is_active + audit columns
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('course_id');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });

        // faculty — add is_active + audit columns
        Schema::table('faculty', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('department_id');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->string('created_by')->nullable()->after('updated_at');
            $table->string('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by']);
            $table->boolean('is_disabled')->default(false)->after('dean_id');
        });

        Schema::table('department_courses', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by']);
            $table->boolean('is_disabled')->default(false)->after('department_course_id');
        });

        Schema::table('faculty_subjects', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'updated_at', 'created_by', 'updated_by']);
        });

        Schema::table('faculty', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'updated_at', 'created_by', 'updated_by']);
        });
    }
};
