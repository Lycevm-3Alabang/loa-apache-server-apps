<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantEndpointOverride extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'method',
        'path',
        'tenant_id',
        'level',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(TenantAppEndpoint::class, 'tenant_id', 'tenant_id')
            ->whereColumn('method', 'method')
            ->whereColumn('path', 'path');
    }

    public static function levelOrdinal(string $level): int
    {
        return match ($level) {
            'read' => 1,
            'write', 'admin' => 2,
            'deny' => -1,
            default => 0,
        };
    }
}
