<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardPurchase extends Model
{
    protected $table = 'gift_card_purchases';

    protected $fillable = [
        'user_id', 'reference', 'reloadly_transaction_id',
        'product_id', 'product_name', 'brand_name', 'country_code',
        'quantity', 'unit_price', 'total_price', 'recipient_currency',
        'sender_amount', 'naira_amount', 'exchange_rate',
        'reloadly_rate', 'reloadly_fee', 'reloadly_discount', 'profit',
        'card_number', 'pin_code', 'redemption_url',
        'redeem_instructions_concise', 'redeem_instructions_verbose',
        'recipient_email', 'recipient_phone', 'logo_url',
        'status', 'reloadly_status', 'error_message',
    ];

    protected $hidden = ['card_number', 'pin_code'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'sender_amount' => 'decimal:2',
        'naira_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'reloadly_rate' => 'decimal:4',
        'reloadly_fee' => 'decimal:2',
        'reloadly_discount' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'successful');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
