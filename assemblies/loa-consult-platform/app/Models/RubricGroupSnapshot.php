<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricGroupSnapshot extends Model
{
    protected $table = 'rubric_group_snapshots';

    protected $fillable = [
        'evaluation_period_id', 'rubric_group_id', 'rubric_group_name',
        'category_name', 'category_display_order', 'item_text',
        'item_display_order', 'item_weight', 'item_id', 'category_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category_display_order' => 'integer',
            'item_display_order' => 'integer',
            'item_weight' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }
}
