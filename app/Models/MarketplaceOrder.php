<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    protected $table = 'marketplace_orders';

    protected $fillable = [
        'user_id', 'reference', 'total_amount', 'delivery_fee', 'grand_total',
        'status', 'delivery_name', 'delivery_phone', 'delivery_address',
        'delivery_city', 'delivery_state', 'tracking_number', 'admin_note',
        'fez_order_no', 'delivery_status', 'delivery_eta',
        'pickup_name', 'pickup_address', 'pickup_phone',
        'payment_method', 'payment_reference', 'monnify_reference', 'payment_status',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'delivery_fee' => 'float',
        'grand_total' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'order_id');
    }
}
