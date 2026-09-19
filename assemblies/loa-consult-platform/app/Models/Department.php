<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'dean_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(DepartmentCourse::class);
    }

    public function sections(): HasMany
    {
        return $this->hasManyThrough(Section::class, DepartmentCourse::class);
    }
}
