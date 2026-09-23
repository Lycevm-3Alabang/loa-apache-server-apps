<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricGroup extends Model
{
    protected $fillable = ['name', 'description', 'seed', 'is_active'];

    protected function casts(): array
    {
        return [
            'seed' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(RubricCategory::class);
    }
}
