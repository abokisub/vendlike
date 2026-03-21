<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create conversion_wallets table
        Schema::create('conversion_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('wallet_type', ['airtime_to_cash', 'gift_card'])->comment('Type of conversion wallet');
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Current wallet balance');
            $table->decimal('total_earned', 15, 2)->default(0.00)->comment('Total amount ever earned');
            $table->decimal('total_withdrawn', 15, 2)->default(0.00)->comment('Total amount withdrawn');
            $table->boolean('is_active')->default(true)->comment('Wallet status');
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('wallet_type');
            $table->unique(['user_id', 'wallet_type']); // One wallet per type per user
            
            // Note: Foreign key constraint removed due to custom user table structure
        });

        // Create conversion_wallet_transactions table
        Schema::create('conversion_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversion_wallet_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('transaction_type', ['credit', 'debit'])->comment('Credit = earning, Debit = withdrawal');
            $table->decimal('amount', 15, 2)->comment('Transaction amount');
            $table->decimal('balance_before', 15, 2)->comment('Balance before transaction');
            $table->decimal('balance_after', 15, 2)->comment('Balance after transaction');
            $table->string('reference', 100)->unique()->comment('Unique transaction reference');
            $table->string('description', 500)->comment('Transaction description');
            $table->enum('source_type', ['airtime_conversion', 'gift_card_sale', 'withdrawal', 'adjustment'])->comment('Source of transaction');
            $table->string('source_reference', 100)->nullable()->comment('Reference to source transaction');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->json('metadata')->nullable()->comment('Additional transaction data');
            $table->timestamp('processed_at')->nullable()->comment('When transaction was processed');
            $table->timestamps();

            // Indexes
            $table->index('conversion_wallet_id');
            $table->index('user_id');
            $table->index('transaction_type');
            $table->index('source_type');
            $table->index('reference');
            $table->index('status');
            $table->index('created_at');
            
            // Note: Foreign key constraints removed due to custom table structures
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_wallet_transactions');
        Schema::dropIfExists('conversion_wallets');
    }
};