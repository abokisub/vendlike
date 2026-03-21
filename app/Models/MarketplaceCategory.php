<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceCategory extends Model
{
    protected $table = 'marketplace_categories';

    protected $fillable = [
        'name', 'slug', 'icon', 'image', 'description', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(MarketplaceProduct::class, 'category_id');
    }
}
