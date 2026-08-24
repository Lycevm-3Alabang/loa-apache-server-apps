<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CertificateTemplate extends Model
{
    use HasFactory;

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'type',
        'html_content',
        'css_content',
        'visibility',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    /**
     * Owner set per specs/components/template-visibility.md §6.1 —
     * unique non-null values of {created_by, updated_by}; never empty for
     * application-written rows.
     */
    public function owners(): array
    {
        return array_values(array_unique(array_filter([
            $this->created_by,
            $this->updated_by,
        ])));
    }

    public function isOwnedBy(string $sub): bool
    {
        return in_array($sub, $this->owners(), true);
    }

    public function isVisibleTo(string $sub, array $groups): bool
    {
        if ($this->visibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        if (in_array('cert-admin', $groups, true)) {
            return true;
        }

        return $this->isOwnedBy($sub);
    }

    public function scopeVisibleTo($query, string $sub, array $groups)
    {
        if (in_array('cert-admin', $groups, true)) {
            return $query;
        }

        return $query->where(function ($q) use ($sub) {
            $q->where('visibility', self::VISIBILITY_PUBLIC)
                ->orWhere(function ($owner) use ($sub) {
                    $owner->where('created_by', $sub)
                        ->orWhere('updated_by', $sub);
                });
        });
    }
}