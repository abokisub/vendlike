<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceVendor extends Model
{
    protected $table = 'marketplace_vendors';

    protected $fillable = [
        'name', 'business_name', 'phone', 'email', 'description', 'is_active',
        'pickup_address', 'pickup_city', 'pickup_state', 'pickup_phone',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(MarketplaceProduct::class, 'vendor_id');
    }
}
