<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EvaluationTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_periods_and_scales_shape(): void
    {
        foreach (['semester_id', 'name', 'is_active', 'rubric_group_id'] as $col) {
            $this->assertTrue(Schema::hasColumn('evaluation_periods', $col), "missing periods.$col");
        }
        foreach (['semester_id', 'evaluation_period_id', 'name', 'value'] as $col) {
            $this->assertTrue(Schema::hasColumn('rating_scales', $col), "missing scales.$col");
        }
    }

    public function test_rubric_family_shape(): void
    {
        $this->assertTrue(Schema::hasColumn('rubric_groups', 'seed'));
        $this->assertTrue(Schema::hasColumn('rubric_categories', 'rubric_group_id'));
        $this->assertTrue(Schema::hasColumn('rubric_items', 'weight'));
        foreach (['evaluation_period_id', 'rubric_group_name', 'item_text', 'item_id'] as $col) {
            $this->assertTrue(Schema::hasColumn('rubric_group_snapshots', $col), "missing snapshots.$col");
        }
    }

    public function test_evaluations_shape(): void
    {
        foreach (['evaluation_period_id', 'semester_id', 'evaluator_id', 'evaluatee_id',
            'faculty_subject_id', 'source', 'status', 'is_invalid', 'is_disabled',
            'remarks', 'submitted_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('evaluations', $col), "missing evaluations.$col");
        }
        foreach (['evaluation_id', 'item_id', 'rating'] as $col) {
            $this->assertTrue(Schema::hasColumn('evaluation_ratings', $col), "missing ratings.$col");
        }
        $this->assertTrue(Schema::hasColumn('evaluation_comments', 'sentiment_label'));
    }

    public function test_results_shape(): void
    {
        foreach (array_merge(
            ['evaluation_period_id', 'semester_id', 'faculty_id', 'department_id',
                'subject_id', 'total_respondents', 'general_rating', 'remarks',
                'is_results_visible', 'computed_at'],
            \App\Models\EvaluationResult::CATEGORIES
        ) as $col) {
            $this->assertTrue(Schema::hasColumn('evaluation_results', $col), "missing results.$col");
        }
    }
}
