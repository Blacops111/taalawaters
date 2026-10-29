<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'payment_terms_days',
        'customer_type',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
