<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'program',
        'department_course_id',
        'is_disabled',
    ];

    protected function casts(): array
    {
        return [
            'is_disabled' => 'boolean',
        ];
    }

    public function departmentCourse(): BelongsTo
    {
        return $this->belongsTo(DepartmentCourse::class);
    }

    public function facultySubjects(): HasMany
    {
        return $this->hasMany(FacultySubject::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
