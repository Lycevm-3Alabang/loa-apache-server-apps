<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantEndpointGrant extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'group_id',
        'method',
        'path',
        'tenant_id',
        'level',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
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
            'write' => 2,
            'admin' => 3,
            'deny' => -1,
            default => -1,
        };
    }
}
