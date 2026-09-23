<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Evaluation extends Model
{
    protected $fillable = [
        'evaluation_period_id', 'semester_id', 'evaluator_id', 'evaluatee_id',
        'faculty_subject_id', 'source', 'status', 'is_invalid', 'is_disabled',
        'remarks', 'submitted_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_invalid' => 'boolean',
            'is_disabled' => 'boolean',
            'is_active' => 'boolean',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'evaluator_id');
    }

    public function evaluatee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluatee_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(FacultySubject::class, 'faculty_subject_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(EvaluationRating::class);
    }

    public function comment(): HasOne
    {
        return $this->hasOne(EvaluationComment::class);
    }
}
