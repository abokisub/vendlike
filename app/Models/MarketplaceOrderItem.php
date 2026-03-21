<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrderItem extends Model
{
    protected $table = 'marketplace_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'unit_price', 'quantity',
        'size', 'color', 'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }
}
