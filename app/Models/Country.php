<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'flag_emoji',
        'flag_url',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get gift card types that support this country
     */
    public function giftCardTypes()
    {
        return $this->belongsToMany(GiftCardType::class, 'gift_card_countries')
                    ->withPivot('country_rate', 'active')
                    ->withTimestamps();
    }

    /**
     * Scope for active countries
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get flag display
     */
    public function getFlagDisplayAttribute()
    {
        return $this->flag_emoji ?: $this->code;
    }
}