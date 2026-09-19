<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $table = 'student_enrollments';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'section_id',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
