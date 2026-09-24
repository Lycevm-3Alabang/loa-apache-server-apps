<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'title',
        'eval_start_date',
        'eval_end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'eval_start_date' => 'date',
            'eval_end_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
