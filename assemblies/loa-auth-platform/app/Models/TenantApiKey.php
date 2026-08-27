<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class TenantApiKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'key_hash',
        'secret_hash',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public static function generateKeyPair(): array
    {
        $key = 'tk_' . bin2hex(random_bytes(6));
        $secret = 'tsk_' . bin2hex(random_bytes(20));

        return [
            'key' => $key,
            'secret' => $secret,
            'key_hash' => hash('sha256', $key),
            'secret_hash' => hash('sha256', $secret),
        ];
    }

    public static function resolveByKey(string $key): ?self
    {
        return static::where('key_hash', hash('sha256', $key))
            ->whereNull('revoked_at')
            ->first();
    }
}
