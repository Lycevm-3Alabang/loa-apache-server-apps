<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationResult extends Model
{
    public const CATEGORIES = [
        'professional_manner',
        'communication_with_student',
        'student_engagement',
        'learning_materials',
        'time_management',
        'experiential_learning',
        'respect_uniqueness',
        'assessment_and_feedback',
    ];

    protected $fillable = [
        'evaluation_period_id', 'semester_id', 'faculty_id', 'department_id',
        'subject_id', 'total_respondents', 'professional_manner',
        'communication_with_student', 'student_engagement', 'learning_materials',
        'time_management', 'experiential_learning', 'respect_uniqueness',
        'assessment_and_feedback', 'general_rating', 'remarks',
        'is_results_visible', 'computed_at', 'is_active',
    ];

    protected function casts(): array
    {
        $casts = [
            'total_respondents' => 'integer',
            'general_rating' => 'decimal:2',
            'is_results_visible' => 'boolean',
            'is_active' => 'boolean',
            'computed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
        foreach (self::CATEGORIES as $category) {
            $casts[$category] = 'decimal:2';
        }

        return $casts;
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'faculty_id');
    }
}
