<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'sent_to',
        'subject',
        'sent_at',
        'sent_by',
        'status',
        'error_message',
    ];

    protected $casts = [
        'id' => 'string',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
