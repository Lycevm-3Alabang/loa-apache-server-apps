<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'key',
        'description',
    ];

    public function routePolicies(): HasMany
    {
        return $this->hasMany(RoutePolicy::class);
    }

    public function groupClaims(): HasMany
    {
        return $this->hasMany(GroupClaim::class);
    }

    public function userOverrides(): HasMany
    {
        return $this->hasMany(UserClaimOverride::class);
    }
}