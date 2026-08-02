<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupClaim extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'group_id',
        'claim_key',
        'scope_type',
        'scope_id',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_key', 'key');
    }

    protected $attributes = [
        'scope_type' => 'none',
    ];
}