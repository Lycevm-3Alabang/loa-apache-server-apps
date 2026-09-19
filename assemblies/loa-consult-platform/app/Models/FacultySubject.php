<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultySubject extends Model
{
    protected $table = 'faculty_subjects';

    public $timestamps = false;

    protected $fillable = [
        'faculty_id',
        'subject_id',
        'section_id',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
