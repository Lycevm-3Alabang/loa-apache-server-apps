<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyAvailabilityRule extends Model
{
    protected $table = 'faculty_availability_rules';

    protected $fillable = [
        'faculty_id',
        'day_of_week',
        'is_blocked',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_blocked' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'faculty_id');
    }
}
