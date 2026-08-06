<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'template_id',
        'recipient_name',
        'recipient_email',
        'certificate_number',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revoke_reason',
        'file_path',
        'metadata',
    ];

    protected $casts = [
        'id' => 'string',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];
}