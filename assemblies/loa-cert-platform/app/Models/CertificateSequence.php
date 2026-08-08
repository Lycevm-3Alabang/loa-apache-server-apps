<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateSequence extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'pattern',
        'next_value',
    ];

    protected $casts = [
        'next_value' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getPrimaryKey()
    {
        return ['organization_id', 'pattern'];
    }
}
