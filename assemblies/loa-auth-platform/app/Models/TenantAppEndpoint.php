<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantAppEndpoint extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'method',
        'path',
        'label',
        'description',
        'required_level',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(TenantEndpointGrant::class, 'tenant_id', 'tenant_id')
            ->whereColumn('method', 'method')
            ->whereColumn('path', 'path');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(TenantEndpointOverride::class, 'tenant_id', 'tenant_id')
            ->whereColumn('method', 'method')
            ->whereColumn('path', 'path');
    }

    public static function matchPath(string $method, string $path, ?string $tenantId): ?self
    {
        $path = self::normalizePath($path);

        $endpoint = self::whereIn('method', [$method, '*'])
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->get()
            ->first(fn ($ep) => $ep->matchesPath($path));

        if ($endpoint) {
            return $endpoint;
        }

        return self::whereIn('method', [$method, '*'])
            ->whereNull('tenant_id')
            ->get()
            ->first(fn ($ep) => $ep->matchesPath($path));
    }

    public function matchesPath(string $path): bool
    {
        $path = self::normalizePath($path);
        $catalogPath = self::normalizePath($this->path);

        if ($catalogPath === $path) {
            return true;
        }

        $pattern = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', preg_quote($catalogPath, '#'));
        $pattern = '#^' . $pattern . '$#';

        return (bool) preg_match($pattern, $path);
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    public function getLevelOrdinalAttribute(): int
    {
        return match ($this->required_level) {
            'read' => 1,
            'write', 'admin' => 2,
            default => 0,
        };
    }
}
