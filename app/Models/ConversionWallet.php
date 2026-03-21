<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ConversionWallet extends Model
{
    use HasFactory;

    protected $table = 'conversion_wallets';

    protected $fillable = [
        'user_id',
        'wallet_type',
        'balance',
        'total_earned',
        'total_withdrawn',
        'is_active'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Wallet types
    const TYPE_AIRTIME_TO_CASH = 'airtime_to_cash';
    const TYPE_GIFT_CARD = 'gift_card';

    /**
     * Get the user that owns the wallet
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for this wallet
     */
    public function transactions()
    {
        return $this->hasMany(ConversionWalletTransaction::class);
    }

    /**
     * Credit the wallet
     */
    public function credit($amount, $description, $sourceType = 'adjustment', $sourceReference = null)
    {
        return DB::transaction(function () use ($amount, $description, $sourceType, $sourceReference) {
            $balanceBefore = $this->balance;
            $balanceAfter = $balanceBefore + $amount;

            // Update wallet balance
            $this->update([
                'balance' => $balanceAfter,
                'total_earned' => $this->total_earned + $amount
            ]);

            // Record transaction
            ConversionWalletTransaction::create([
                'conversion_wallet_id' => $this->id,
                'user_id' => $this->user_id,
                'transaction_type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => 'CWT' . time() . rand(1000, 9999),
                'description' => $description,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'status' => 'completed',
                'processed_at' => now()
            ]);

            return $this->fresh();
        });
    }

    /**
     * Debit the wallet
     */
    public function debit($amount, $description, $sourceType = 'withdrawal', $sourceReference = null)
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        return DB::transaction(function () use ($amount, $description, $sourceType, $sourceReference) {
            $balanceBefore = $this->balance;
            $balanceAfter = $balanceBefore - $amount;

            // Update wallet balance
            $this->update([
                'balance' => $balanceAfter,
                'total_withdrawn' => $this->total_withdrawn + $amount
            ]);

            // Record transaction
            ConversionWalletTransaction::create([
                'conversion_wallet_id' => $this->id,
                'user_id' => $this->user_id,
                'transaction_type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => 'CWT' . time() . rand(1000, 9999),
                'description' => $description,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'status' => 'completed',
                'processed_at' => now()
            ]);

            return $this->fresh();
        });
    }

    /**
     * Get or create A2Cash wallet for user
     */
    public static function getOrCreateA2CashWallet($userId)
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'wallet_type' => self::TYPE_AIRTIME_TO_CASH
            ],
            [
                'balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'is_active' => true
            ]
        );
    }

    /**
     * Get or create Gift Card wallet for user
     */
    public static function getOrCreateGiftCardWallet($userId)
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'wallet_type' => self::TYPE_GIFT_CARD
            ],
            [
                'balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'is_active' => true
            ]
        );
    }

    /**
     * Get or create wallet for user (legacy method)
     */
    public static function getOrCreateForUser($userId)
    {
        // Default to A2Cash wallet for backward compatibility
        return self::getOrCreateA2CashWallet($userId);
    }
}