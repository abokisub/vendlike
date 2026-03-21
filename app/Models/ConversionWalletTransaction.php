<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversion_wallet_id',
        'user_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'source_type',
        'source_reference',
        'status',
        'metadata',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    // Transaction types
    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';

    // Source types
    const SOURCE_AIRTIME_CONVERSION = 'airtime_conversion';
    const SOURCE_GIFT_CARD_SALE = 'gift_card_sale';
    const SOURCE_WITHDRAWAL = 'withdrawal';
    const SOURCE_ADJUSTMENT = 'adjustment';

    // Status types
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the conversion wallet that owns the transaction
     */
    public function conversionWallet()
    {
        return $this->belongsTo(ConversionWallet::class);
    }

    /**
     * Get the user that owns the transaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for credit transactions
     */
    public function scopeCredits($query)
    {
        return $query->where('transaction_type', self::TYPE_CREDIT);
    }

    /**
     * Scope for debit transactions
     */
    public function scopeDebits($query)
    {
        return $query->where('transaction_type', self::TYPE_DEBIT);
    }

    /**
     * Scope for completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for specific source type
     */
    public function scopeBySource($query, $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Get transaction type color
     */
    public function getTypeColorAttribute()
    {
        return $this->transaction_type === self::TYPE_CREDIT ? 'success' : 'error';
    }

    /**
     * Get formatted amount with sign
     */
    public function getFormattedAmountAttribute()
    {
        $sign = $this->transaction_type === self::TYPE_CREDIT ? '+' : '-';
        return $sign . '₦' . number_format($this->amount, 2);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_PENDING => 'warning',
            self::STATUS_FAILED => 'error',
            self::STATUS_CANCELLED => 'secondary',
            default => 'secondary'
        };
    }
}