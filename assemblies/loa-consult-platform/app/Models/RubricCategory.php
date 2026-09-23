<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricCategory extends Model
{
    protected $fillable = [
        'rubric_group_id', 'name', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(RubricGroup::class, 'rubric_group_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RubricItem::class, 'category_id');
    }
}
