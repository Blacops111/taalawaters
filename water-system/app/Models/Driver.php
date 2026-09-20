<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'license_number',
        'license_expiry',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
