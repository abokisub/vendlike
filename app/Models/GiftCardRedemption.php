<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCardRedemption extends Model
{
    use HasFactory;

    protected $appends = ['image_urls', 'status_color', 'image_paths'];

    protected $fillable = [
        'user_id',
        'gift_card_type_id',
        'card_code',
        'card_amount',
        'expected_naira',
        'actual_naira',
        'image_path',
        'additional_images',
        'status',
        'admin_notes',
        'reference',
        'processed_by',
        'processed_at',
        'redemption_method'
    ];

    protected $casts = [
        'card_amount' => 'decimal:2',
        'expected_naira' => 'decimal:2',
        'actual_naira' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    /**
     * Get all uploaded file paths as array
     */
    public function getImagePathsAttribute()
    {
        // Try additional_images first (JSON array of all paths)
        if ($this->additional_images) {
            $decoded = json_decode($this->additional_images, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }
        // Fallback to single image_path
        if ($this->image_path) {
            return [$this->image_path];
        }
        return [];
    }

    /**
     * Get all uploaded file URLs
     */
    public function getImageUrlsAttribute()
    {
        return collect($this->image_paths)->map(function ($path) {
            return asset('storage/' . $path);
        })->toArray();
    }

    /**
     * Check if a file path is a PDF
     */
    public static function isPdf($path)
    {
        return str_ends_with(strtolower($path), '.pdf');
    }

    /**
     * Get the user that owns the redemption
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the gift card type
     */
    public function giftCardType()
    {
        return $this->belongsTo(GiftCardType::class);
    }

    /**
     * Get the admin who processed this redemption
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope for pending redemptions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved redemptions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for declined redemptions
     */
    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Generate unique reference
     */
    public static function generateReference()
    {
        do {
            $reference = 'GC_' . time() . rand(1000, 9999);
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'declined' => 'error',
            'processing' => 'info',
            default => 'default'
        };
    }
}