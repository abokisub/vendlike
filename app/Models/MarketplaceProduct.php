<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceProduct extends Model
{
    protected $table = 'marketplace_products';

    protected $fillable = [
        'category_id', 'vendor_id', 'name', 'slug', 'description', 'price', 'discount_price',
        'stock', 'weight', 'images', 'sizes', 'colors', 'is_active', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'discount_price' => 'float',
        'weight' => 'float',
        'images' => 'array',
        'sizes' => 'array',
        'colors' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function vendor()
    {
        return $this->belongsTo(MarketplaceVendor::class, 'vendor_id');
    }

    public function getEffectivePriceAttribute()
    {
        return $this->discount_price && $this->discount_price > 0 ? $this->discount_price : $this->price;
    }

    public function getImageUrlsAttribute()
    {
        if (!$this->images || !is_array($this->images)) return [];
        return array_map(function ($path) {
            if (str_starts_with($path, 'http')) return $path;
            return url('storage/' . $path);
        }, $this->images);
    }
}
