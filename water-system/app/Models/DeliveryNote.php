<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNote extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference',
        'sales_order_id',
        'vehicle_assignment_id',
        'status',
        'delivery_address',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'scheduled_at',
        'dispatched_at',
        'delivered_at',
        'created_by',
        'notes',
    ];

    protected $hidden = [
        'confirmation_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'confirmation_code_generated_at' => 'datetime',
            'confirmation_code_expires_at' => 'datetime',
            'confirmation_code_last_sent_at' => 'datetime',
            'confirmation_code_sms_sent_at' => 'datetime',
            'confirmation_code_email_sent_at' => 'datetime',
            'confirmation_code_failed_attempts' => 'integer',
            'confirmation_code_locked_at' => 'datetime',
            'confirmation_code_verified_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function vehicleAssignment(): BelongsTo
    {
        return $this->belongsTo(VehicleAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmationVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmation_code_verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
