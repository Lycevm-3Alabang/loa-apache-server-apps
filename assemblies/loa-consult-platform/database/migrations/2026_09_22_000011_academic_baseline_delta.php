<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B1 baseline delta (data-model.md Final v1.3 §7).
 *
 * - sections: course-linked (department_course_id FK NOT NULL + program),
 *   drop subject_id/schedule/room + legacy unique; UNIQUE(name, program).
 * - faculty_subjects: + semester_id FK NULL.
 * - student_enrollments: + section_id/semester_id/faculty_subject_id FK NULL,
 *   drop subject_id + legacy unique; UNIQUE(student_id, faculty_subject_id,
 *   semester_id).
 *
 * Fully guarded so it converges from any half-migrated state. Notes:
 * - DDL inside a Schema::table closure builds on close, so per-statement
 *   try/catch cannot catch SQL errors — existence is checked up front via
 *   information_schema (+ hasColumn for columns) instead.
 * - MySQL requires DROP FOREIGN KEY before DROP COLUMN — FK drops are
 *   explicit and fkExists-guarded.
 *
 * Slice-C deltas (evaluations.is_disabled/remarks,
 * rating_scales.evaluation_period_id) are NOT included — separate slice.
 */
return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function fkExists(string $table, string $fk): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $fk)
            ->exists();
    }

    public function up(): void
    {
        if ($this->indexExists('sections', 'sections_name_subject_id_unique')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropUnique(['name', 'subject_id']);
            });
        }

        if ($this->fkExists('sections', 'sections_subject_id_foreign')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
            });
        }

        Schema::table('sections', function (Blueprint $table) {
            foreach (['subject_id', 'schedule', 'room'] as $col) {
                if (Schema::hasColumn('sections', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (!Schema::hasColumn('sections', 'program')) {
                $table->string('program');
            }
            if (!Schema::hasColumn('sections', 'department_course_id')) {
                $table->foreignId('department_course_id')->constrained('department_courses')->cascadeOnDelete();
            }
        });

        if (!$this->indexExists('sections', 'sections_name_program_unique')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->unique(['name', 'program']);
            });
        }

        Schema::table('faculty_subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('faculty_subjects', 'semester_id')) {
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->cascadeOnDelete();
            }
        });

        // The legacy unique backs BOTH FKs (InnoDB uses it for student_id),
        // so drop both FKs before the unique, then relink student_id after.
        foreach (['student_id', 'subject_id'] as $col) {
            if ($this->fkExists('student_enrollments', "student_enrollments_{$col}_foreign")) {
                Schema::table('student_enrollments', function (Blueprint $table) use ($col) {
                    $table->dropForeign([$col]);
                });
            }
        }

        if ($this->indexExists('student_enrollments', 'student_enrollments_student_id_subject_id_unique')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->dropUnique(['student_id', 'subject_id']);
            });
        }

        $relinkStudent = !$this->fkExists('student_enrollments', 'student_enrollments_student_id_foreign');

        Schema::table('student_enrollments', function (Blueprint $table) use ($relinkStudent) {
            if (Schema::hasColumn('student_enrollments', 'subject_id')) {
                $table->dropColumn('subject_id');
            }
            if (!Schema::hasColumn('student_enrollments', 'section_id')) {
                $table->foreignId('section_id')->nullable()->constrained('sections')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('student_enrollments', 'semester_id')) {
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('student_enrollments', 'faculty_subject_id')) {
                $table->foreignId('faculty_subject_id')->nullable()->constrained('faculty_subjects')->cascadeOnDelete();
            }
            if ($relinkStudent) {
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            }
        });

        if (!$this->indexExists('student_enrollments', 'enr_stu_map_sem_unique')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->unique(['student_id', 'faculty_subject_id', 'semester_id'], 'enr_stu_map_sem_unique');
            });
        }
    }

    public function down(): void
    {
        // Trio unique backs student_id too — drop all FKs first, relink after.
        foreach ([
            'student_enrollments_student_id_foreign' => 'student_id',
            'student_enrollments_faculty_subject_id_foreign' => 'faculty_subject_id',
            'student_enrollments_semester_id_foreign' => 'semester_id',
            'student_enrollments_section_id_foreign' => 'section_id',
        ] as $fk => $col) {
            if ($this->fkExists('student_enrollments', $fk)) {
                Schema::table('student_enrollments', function (Blueprint $table) use ($col) {
                    $table->dropForeign([$col]);
                });
            }
        }

        if ($this->indexExists('student_enrollments', 'enr_stu_map_sem_unique')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->dropUnique('enr_stu_map_sem_unique');
            });
        }

        $relinkStudent = !$this->fkExists('student_enrollments', 'student_enrollments_student_id_foreign');

        Schema::table('student_enrollments', function (Blueprint $table) use ($relinkStudent) {
            foreach (['faculty_subject_id', 'semester_id', 'section_id'] as $col) {
                if (Schema::hasColumn('student_enrollments', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (!Schema::hasColumn('student_enrollments', 'subject_id')) {
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            }
            if ($relinkStudent) {
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            }
        });

        if (!$this->indexExists('student_enrollments', 'student_enrollments_student_id_subject_id_unique')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->unique(['student_id', 'subject_id']);
            });
        }

        if ($this->fkExists('faculty_subjects', 'faculty_subjects_semester_id_foreign')) {
            Schema::table('faculty_subjects', function (Blueprint $table) {
                $table->dropForeign(['semester_id']);
            });
        }

        Schema::table('faculty_subjects', function (Blueprint $table) {
            if (Schema::hasColumn('faculty_subjects', 'semester_id')) {
                $table->dropColumn('semester_id');
            }
        });

        if ($this->indexExists('sections', 'sections_name_program_unique')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropUnique(['name', 'program']);
            });
        }

        if ($this->fkExists('sections', 'sections_department_course_id_foreign')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropForeign(['department_course_id']);
            });
        }

        Schema::table('sections', function (Blueprint $table) {
            if (Schema::hasColumn('sections', 'department_course_id')) {
                $table->dropColumn('department_course_id');
            }
            if (Schema::hasColumn('sections', 'program')) {
                $table->dropColumn('program');
            }
            if (!Schema::hasColumn('sections', 'subject_id')) {
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('sections', 'schedule')) {
                $table->string('schedule')->nullable();
            }
            if (!Schema::hasColumn('sections', 'room')) {
                $table->string('room')->nullable();
            }
        });

        if (!$this->indexExists('sections', 'sections_name_subject_id_unique')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->unique(['name', 'subject_id']);
            });
        }
    }
};
