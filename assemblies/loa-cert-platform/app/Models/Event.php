<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'template_id',
        'email_template_id',
        'name',
        'description',
        'event_date',
        'location',
        'organizer',
        'certificate_title',
        'certificate_number_pattern',
        'valid_until',
        'status',
        'is_public',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'event_date' => 'date',
        'valid_until' => 'date',
        'is_public' => 'boolean',
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

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Event visibility mirrors the template-visibility model but simpler:
     * a boolean flag plus a single author (created_by). Public events are
     * visible to all cert staff; private events only to the author and
     * cert-admin. cert-admin always sees everything.
     */
    public function isVisibleTo(?string $sub, array $groups): bool
    {
        if ($this->is_public) {
            return true;
        }

        if (in_array('cert-admin', $groups, true)) {
            return true;
        }

        return $sub !== null && $this->created_by !== null && $this->created_by === $sub;
    }

    public function scopeVisibleTo($query, ?string $sub, array $groups)
    {
        if (in_array('cert-admin', $groups, true)) {
            return $query;
        }

        return $query->where(function ($q) use ($sub) {
            $q->where('is_public', true);
            if ($sub !== null) {
                $q->orWhere('created_by', $sub);
            }
        });
    }
}