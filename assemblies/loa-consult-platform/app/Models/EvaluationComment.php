<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationComment extends Model
{
    protected $fillable = [
        'evaluation_id', 'comment', 'sentiment_label', 'sentiment_score',
        'sentiment_analyzed_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sentiment_score' => 'decimal:4',
            'sentiment_analyzed_at' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
