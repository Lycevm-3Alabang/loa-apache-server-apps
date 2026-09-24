<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = [
        'student_id',
        'faculty_id',
        'session_group_id',
        'created_by_email',
        'meeting_type',
        'date',
        'start_time',
        'end_time',
        'title',
        'description',
        'status',
        'action_taken',
        'additional_remarks',
        'teams_link',
        'teams_sync_status',
        'teams_sync_retries',
        'teams_sync_error',
        'teams_sync_last_attempt',
        'requested_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'teams_sync_retries' => 'integer',
            'is_active' => 'boolean',
            'requested_at' => 'datetime',
            'teams_sync_last_attempt' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'faculty_id');
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(AppointmentTimeSlot::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(AppointmentAttendee::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AppointmentFile::class);
    }
}
