<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->boolean('is_active')->default(true)->after('room');
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
    }

    public function down(): void
    {
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
            $table->boolean('is_disabled')->default(false)->after('room');
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'created_by', 'updated_by']);
        });
    }
};
