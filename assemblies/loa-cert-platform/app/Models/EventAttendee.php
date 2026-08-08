<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventAttendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'organization_id',
        'name',
        'email',
        'attended',
        'completed',
        'attended_at',
        'completed_at',
        'certificate_id',
        'certificate_number',
        'metadata',
    ];

    protected $casts = [
        'id' => 'string',
        'attended' => 'boolean',
        'completed' => 'boolean',
        'attended_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];
}