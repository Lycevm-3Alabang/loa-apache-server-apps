<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentCourse extends Model
{
    protected $table = 'department_courses';

    public $timestamps = false;

    protected $fillable = [
        'department_id',
        'name',
        'code',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
