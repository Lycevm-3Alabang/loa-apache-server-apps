<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'template_id',
        'email_template_id',
        'name',
        'description',
        'event_date',
        'location',
        'organizer',
        'certificate_title',
        'certificate_number_pattern',
        'valid_until',
        'status',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'event_date' => 'date',
        'valid_until' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}