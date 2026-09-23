<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C1 evaluation tables (data-model.md Final v1.3 §3.3, §7 order).
 *
 * Notes:
 * - students/employees PKs are UUID → foreignUuid links.
 * - evaluations trio unique + results trio unique exceed MySQL's 64-char
 *   limit: results reuses the legacy name
 *   `eval_results_period_faculty_subject_unique` (supabase-schema.sql M~1935);
 *   evaluations uses `eval_period_evaluator_map_unique` in the same style.
 * - Results category columns are the 8 legacy seed names, snake_cased
 *   (verified against supabase-schema.sql + evaluation-report consumers);
 *   "9×" in §3.4 is loose wording for the category family.
 * - Snapshot/rubric_group_id intentionally has NO FK (point-in-time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('rubric_group_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('rating_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_period_id')->nullable()->constrained('evaluation_periods')->cascadeOnDelete();
            $table->string('name');
            $table->integer('value');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(['semester_id', 'value']);
        });

        Schema::create('rubric_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('seed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('rubric_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('rubric_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('rubric_categories')->cascadeOnDelete();
            $table->text('text');
            $table->integer('display_order')->default(0);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('rubric_group_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rubric_group_id')->nullable();
            $table->string('rubric_group_name');
            $table->string('category_name');
            $table->integer('category_display_order')->default(0);
            $table->text('item_text');
            $table->integer('item_display_order')->default(0);
            $table->decimal('item_weight', 5, 2)->default(1.00);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('evaluator_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('evaluatee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('faculty_subject_id')->nullable()->constrained('faculty_subjects')->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('status')->default('DRAFT');
            $table->boolean('is_invalid')->default(false);
            $table->boolean('is_disabled')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(
                ['evaluation_period_id', 'evaluator_id', 'faculty_subject_id'],
                'eval_period_evaluator_map_unique'
            );
        });

        Schema::create('evaluation_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('rubric_items')->cascadeOnDelete();
            $table->integer('rating');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(['evaluation_id', 'item_id']);
        });

        Schema::create('evaluation_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->decimal('sentiment_score', 5, 4)->nullable();
            $table->string('sentiment_label')->nullable();
            $table->timestamp('sentiment_analyzed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('faculty_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('total_respondents')->default(0);
            $table->decimal('professional_manner', 5, 2)->nullable();
            $table->decimal('communication_with_student', 5, 2)->nullable();
            $table->decimal('student_engagement', 5, 2)->nullable();
            $table->decimal('learning_materials', 5, 2)->nullable();
            $table->decimal('time_management', 5, 2)->nullable();
            $table->decimal('experiential_learning', 5, 2)->nullable();
            $table->decimal('respect_uniqueness', 5, 2)->nullable();
            $table->decimal('assessment_and_feedback', 5, 2)->nullable();
            $table->decimal('general_rating', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_results_visible')->default(false);
            $table->timestamp('computed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unique(
                ['evaluation_period_id', 'faculty_id', 'subject_id'],
                'eval_results_period_faculty_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('evaluation_comments');
        Schema::dropIfExists('evaluation_ratings');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('rubric_group_snapshots');
        Schema::dropIfExists('rubric_items');
        Schema::dropIfExists('rubric_categories');
        Schema::dropIfExists('rubric_groups');
        Schema::dropIfExists('rating_scales');
        Schema::dropIfExists('evaluation_periods');
    }
};
