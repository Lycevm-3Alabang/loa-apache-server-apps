<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'slug',
        'name',
        'status',
        'app_url',
        'dev_app_url',
        'redirect_origins',
        'dev_redirect_origins',
    ];

    protected $casts = [
        'redirect_origins' => 'array',
        'dev_redirect_origins' => 'array',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tenants')->withTimestamps();
    }

    public function userGroups(): HasMany
    {
        return $this->hasMany(UserGroup::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function effectiveAppUrl(): ?string
    {
        return app()->environment('production')
            ? $this->app_url
            : ($this->dev_app_url ?? $this->app_url);
    }

    public function effectiveRedirectOrigins(): array
    {
        return app()->environment('production')
            ? ($this->redirect_origins ?? [])
            : ($this->dev_redirect_origins ?? $this->redirect_origins ?? []);
    }
}
