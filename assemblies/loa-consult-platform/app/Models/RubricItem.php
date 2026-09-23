<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricItem extends Model
{
    protected $fillable = [
        'category_id', 'text', 'display_order', 'weight', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RubricCategory::class, 'category_id');
    }
}
