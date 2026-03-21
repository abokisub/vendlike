<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('gift_card_purchases')) {
            DB::statement("
                CREATE TABLE gift_card_purchases (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    reference VARCHAR(50) NOT NULL UNIQUE,
                    reloadly_transaction_id BIGINT UNSIGNED NULL,
                    product_id INT UNSIGNED NOT NULL COMMENT 'Reloadly product ID',
                    product_name VARCHAR(255) NOT NULL,
                    brand_name VARCHAR(255) NULL,
                    country_code VARCHAR(10) NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 1,
                    unit_price DECIMAL(12,2) NOT NULL COMMENT 'Price per card in recipient currency',
                    total_price DECIMAL(12,2) NOT NULL COMMENT 'Total in recipient currency',
                    recipient_currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                    sender_amount DECIMAL(12,2) NOT NULL COMMENT 'Amount charged in NGN (our cost from Reloadly)',
                    naira_amount DECIMAL(12,2) NOT NULL COMMENT 'Amount charged to user in NGN (with markup)',
                    exchange_rate DECIMAL(12,4) NOT NULL COMMENT 'Markup percentage applied',
                    reloadly_rate DECIMAL(12,4) NULL COMMENT 'Reloadly FX rate',
                    reloadly_fee DECIMAL(12,2) NULL COMMENT 'Fee charged by Reloadly',
                    reloadly_discount DECIMAL(12,2) NULL COMMENT 'Discount from Reloadly',
                    profit DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Our profit on this transaction',
                    card_number TEXT NULL COMMENT 'Gift card redeem code',
                    pin_code VARCHAR(255) NULL COMMENT 'Gift card PIN',
                    redemption_url TEXT NULL COMMENT 'URL to redeem the card',
                    redeem_instructions_concise TEXT NULL,
                    redeem_instructions_verbose TEXT NULL,
                    recipient_email VARCHAR(255) NULL,
                    recipient_phone VARCHAR(50) NULL,
                    logo_url TEXT NULL,
                    status ENUM('pending','successful','failed','refunded','processing') NOT NULL DEFAULT 'pending',
                    reloadly_status VARCHAR(50) NULL COMMENT 'Raw status from Reloadly',
                    error_message TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_reference (reference),
                    INDEX idx_status (status),
                    INDEX idx_reloadly_txn (reloadly_transaction_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Add buy_giftcard_markup to settings table if not exists
        if (Schema::hasTable('settings')) {
            if (!Schema::hasColumn('settings', 'buy_giftcard_markup')) {
                DB::statement("ALTER TABLE settings ADD COLUMN buy_giftcard_markup DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Percentage markup on Reloadly cost price'");
            }
            if (!Schema::hasColumn('settings', 'buy_giftcard_provider')) {
                DB::statement("ALTER TABLE settings ADD COLUMN buy_giftcard_provider VARCHAR(50) NOT NULL DEFAULT 'reloadly' COMMENT 'Gift card API provider'");
            }
            if (!Schema::hasColumn('settings', 'buy_giftcard_status')) {
                DB::statement("ALTER TABLE settings ADD COLUMN buy_giftcard_status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=enabled, 0=disabled'");
            }
            if (Schema::hasColumn('settings', 'buy_giftcard_rate')) {
                DB::statement("ALTER TABLE settings DROP COLUMN buy_giftcard_rate");
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('gift_card_purchases');
        if (Schema::hasTable('settings')) {
            if (Schema::hasColumn('settings', 'buy_giftcard_rate')) {
                DB::statement("ALTER TABLE settings DROP COLUMN buy_giftcard_rate");
            }
            if (Schema::hasColumn('settings', 'buy_giftcard_status')) {
                DB::statement("ALTER TABLE settings DROP COLUMN buy_giftcard_status");
            }
        }
    }
};
