<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B5 carry-forward (endpoints-academic.md §7 + data-model.md L68).
 *
 * faculty_subjects.section_id was a repo-layer field beyond the DDL at spec
 * time ("verify at build, do not drop silently"). The Final data-model L68
 * shape includes it, so it lands here (NULL FK → sections CASCADE) with the
 * spec'd UNIQUE(subject_id, section_id) backing the 409 path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('faculty_subjects', 'section_id')) {
                $table->foreignId('section_id')->nullable()->constrained('sections')->cascadeOnDelete();
            }
        });

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'faculty_subjects')
            ->where('index_name', 'faculty_subjects_subject_id_section_id_unique')
            ->exists();

        if (!$exists) {
            Schema::table('faculty_subjects', function (Blueprint $table) {
                $table->unique(['subject_id', 'section_id']);
            });
        }
    }

    public function down(): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'faculty_subjects')
            ->where('index_name', 'faculty_subjects_subject_id_section_id_unique')
            ->exists();

        if ($exists) {
            Schema::table('faculty_subjects', function (Blueprint $table) {
                $table->dropUnique(['subject_id', 'section_id']);
            });
        }

        Schema::table('faculty_subjects', function (Blueprint $table) {
            if (Schema::hasColumn('faculty_subjects', 'section_id')) {
                $table->dropColumn('section_id');
            }
        });
    }
};
