<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Append-only audit evidence (admin-audit-log.md §0/§3; kernels/audit.md v1.0).
 * Immutable by contract: no update/delete methods or endpoints may ever exist.
 */
class AuditLog extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'actor_id',
        'actor_email',
        'action',
        'source',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
