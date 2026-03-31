<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [

        'product_id',
        'quantity_sold',
        'price',
        'total_amount',
        'sale_date'

    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
