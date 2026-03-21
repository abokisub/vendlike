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
        // Gift Card Types (Admin manages these)
        Schema::create('gift_card_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->decimal('rate', 5, 2);
            $table->decimal('min_amount', 10, 2)->default(10.00);
            $table->decimal('max_amount', 10, 2)->default(500.00);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('icon', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Gift Card Redemption Requests
        Schema::create('gift_card_redemptions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->unsignedBigInteger('gift_card_type_id');
            $table->string('card_code', 255);
            $table->decimal('card_amount', 10, 2);
            $table->decimal('expected_naira', 10, 2);
            $table->decimal('actual_naira', 10, 2)->nullable();
            $table->string('image_path', 500);
            $table->enum('status', ['pending', 'approved', 'declined', 'processing'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->string('reference', 50)->unique();
            $table->integer('processed_by')->unsigned()->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('gift_card_type_id');
            $table->index('status');
            $table->index('reference');
        });

        // Conversion Wallet (Separate from main wallet)
        Schema::create('conversion_wallet', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned()->unique();
            $table->decimal('balance', 10, 2)->default(0.00);
            $table->decimal('total_earned', 10, 2)->default(0.00);
            $table->decimal('total_withdrawn', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // Conversion Wallet Transactions
        Schema::create('conversion_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 10, 2);
            $table->string('description', 255);
            $table->string('reference', 50);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_wallet_transactions');
        Schema::dropIfExists('conversion_wallet');
        Schema::dropIfExists('gift_card_redemptions');
        Schema::dropIfExists('gift_card_types');
    }
};