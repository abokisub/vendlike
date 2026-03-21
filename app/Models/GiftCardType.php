<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCardType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rate',
        'physical_rate',
        'ecode_rate',
        'previous_rate',
        'rate_change',
        'rate_trend',
        'min_amount',
        'max_amount',
        'status',
        'icon',
        'logo_path',
        'description',
        'supported_countries',
        'redemption_type',
        'require_code_for_physical',
        'sort_order',
        'speed'
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'physical_rate' => 'decimal:2',
        'ecode_rate' => 'decimal:2',
        'previous_rate' => 'decimal:2',
        'rate_change' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'supported_countries' => 'array',
        'require_code_for_physical' => 'boolean',
    ];

    /**
     * Get all redemptions for this gift card type
     */
    public function redemptions()
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    /**
     * Get supported countries for this gift card
     */
    public function countries()
    {
        return $this->belongsToMany(Country::class, 'gift_card_countries')
                    ->withPivot('country_rate', 'active')
                    ->withTimestamps();
    }

    /**
     * Scope for active gift card types
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for ordered gift cards
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Calculate conversion amount based on redemption method
     * rate = display rate (shown on list)
     * physical_rate = actual rate for physical cards
     * ecode_rate = actual rate for e-code cards
     * Falls back to display rate if specific rate not set
     */
    public function calculateConversion($cardAmount, $redemptionMethod = null, $countryId = null)
    {
        // Pick the right rate based on method
        if ($redemptionMethod === 'physical' && $this->physical_rate) {
            $rate = $this->physical_rate;
        } elseif ($redemptionMethod === 'code' && $this->ecode_rate) {
            $rate = $this->ecode_rate;
        } else {
            $rate = $this->rate; // fallback to display rate
        }
        
        // Check if there's a specific rate for the country
        if ($countryId) {
            $countryRate = $this->countries()->where('country_id', $countryId)->first();
            if ($countryRate && $countryRate->pivot->country_rate) {
                $rate = $countryRate->pivot->country_rate;
            }
        }
        
        return $cardAmount * $rate;
    }

    /**
     * Check if amount is within limits
     */
    public function isAmountValid($amount)
    {
        return $amount >= $this->min_amount && $amount <= $this->max_amount;
    }

    /**
     * Update rate and calculate change
     */
    public function updateRate($newRate)
    {
        $this->previous_rate = $this->rate;
        $this->rate_change = $newRate - $this->rate;
        
        if ($this->rate_change > 0) {
            $this->rate_trend = 'up';
        } elseif ($this->rate_change < 0) {
            $this->rate_trend = 'down';
        } else {
            $this->rate_trend = 'stable';
        }
        
        $this->rate = $newRate;
        $this->save();
    }

    /**
     * Get rate change percentage
     */
    public function getRateChangePercentageAttribute()
    {
        if (!$this->previous_rate || $this->previous_rate == 0) {
            return 0;
        }
        
        return round(($this->rate_change / $this->previous_rate) * 100, 2);
    }

    /**
     * Get formatted rate change
     */
    public function getFormattedRateChangeAttribute()
    {
        $percentage = $this->rate_change_percentage;
        $sign = $percentage > 0 ? '+' : '';
        return $sign . $percentage . '%';
    }

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo_path) {
            return asset('storage/' . $this->logo_path);
        }
        return null;
    }
}