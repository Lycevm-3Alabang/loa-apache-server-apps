<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fully guarded (hasColumn) so it converges from any half-migrated
     * state — 000010 previously failed partway on fresh and partial DBs.
     * Source of truth per table: 000001/000002/000003/000004/000007 create
     * migrations + data-model.md Final (is_active everywhere; sections never
     * had is_disabled).
     */
    public function up(): void
    {
        // departments — replace is_disabled with is_active, add audit columns
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'is_disabled')) {
                $table->dropColumn('is_disabled');
            }
            if (!Schema::hasColumn('departments', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('departments', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('departments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (!Schema::hasColumn('departments', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (!Schema::hasColumn('departments', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });

        // department_courses — add audit columns
        Schema::table('department_courses', function (Blueprint $table) {
            if (!Schema::hasColumn('department_courses', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('department_courses', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (!Schema::hasColumn('department_courses', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (!Schema::hasColumn('department_courses', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });

        // subjects — add audit columns
        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('subjects', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('subjects', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (!Schema::hasColumn('subjects', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (!Schema::hasColumn('subjects', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });

        // sections — audit columns only (000004 already ships
        // is_active/created_at/updated_at; it never had is_disabled)
        Schema::table('sections', function (Blueprint $table) {
            if (!Schema::hasColumn('sections', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (!Schema::hasColumn('sections', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });

        // semesters — add audit columns
        Schema::table('semesters', function (Blueprint $table) {
            if (!Schema::hasColumn('semesters', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (!Schema::hasColumn('semesters', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (!Schema::hasColumn('semesters', 'updated_by')) {
                $table->string('updated_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $drop = [];
            foreach (['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by'] as $col) {
                if (Schema::hasColumn('departments', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
            if (!Schema::hasColumn('departments', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false);
            }
        });

        Schema::table('department_courses', function (Blueprint $table) {
            $drop = [];
            foreach (['is_active', 'updated_at', 'created_by', 'updated_by'] as $col) {
                if (Schema::hasColumn('department_courses', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            $drop = [];
            foreach (['is_active', 'created_at', 'updated_at', 'created_by', 'updated_by'] as $col) {
                if (Schema::hasColumn('subjects', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('sections', function (Blueprint $table) {
            $drop = [];
            foreach (['created_by', 'updated_by'] as $col) {
                if (Schema::hasColumn('sections', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('semesters', function (Blueprint $table) {
            $drop = [];
            foreach (['updated_at', 'created_by', 'updated_by'] as $col) {
                if (Schema::hasColumn('semesters', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
