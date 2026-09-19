<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'app_users';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'department_id',
        'course_id',
        'semester_id',
        'is_disabled',
        'last_login_at',
        'onboarding_version',
    ];

    protected function casts(): array
    {
        return [
            'is_disabled' => 'boolean',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
            'onboarding_version' => 'integer',
        ];
    }

    public function departmentCourse(): BelongsTo
    {
        return $this->belongsTo(DepartmentCourse::class, 'course_id');
    }
}
