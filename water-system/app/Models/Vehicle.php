<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    public const TYPE_MOTORBIKE = 'motorbike';
    public const TYPE_TANKER_TRUCK = 'tanker_truck';
    public const TYPE_DELIVERY_VAN = 'delivery_van';
    public const TYPE_OTHER = 'other';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'registration_number',
        'name',
        'vehicle_type',
        'capacity_quantity',
        'capacity_unit',
        'status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}
