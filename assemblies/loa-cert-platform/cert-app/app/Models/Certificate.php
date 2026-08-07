<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CertificateEmail::class);
    }

    public function getStatusAttribute(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
