<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationRating extends Model
{
    protected $fillable = ['evaluation_id', 'item_id', 'rating', 'is_active'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RubricItem::class, 'item_id');
    }
}
