<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add Sudo-specific columns to virtual_cards table using raw SQL
        if (Schema::hasTable('virtual_cards')) {
            if (!Schema::hasColumn('virtual_cards', 'sudo_card_id')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `sudo_card_id` VARCHAR(255) NULL AFTER `card_id`");
            }
            if (!Schema::hasColumn('virtual_cards', 'sudo_customer_id')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `sudo_customer_id` VARCHAR(255) NULL AFTER `sudo_card_id`");
            }
            if (!Schema::hasColumn('virtual_cards', 'masked_pan')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `masked_pan` VARCHAR(255) NULL AFTER `sudo_customer_id`");
            }
            if (!Schema::hasColumn('virtual_cards', 'brand')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `brand` VARCHAR(255) NULL AFTER `masked_pan`");
            }
            if (!Schema::hasColumn('virtual_cards', 'expiry_month')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `expiry_month` VARCHAR(2) NULL AFTER `brand`");
            }
            if (!Schema::hasColumn('virtual_cards', 'expiry_year')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `expiry_year` VARCHAR(4) NULL AFTER `expiry_month`");
            }
            if (!Schema::hasColumn('virtual_cards', 'card_balance')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `card_balance` DECIMAL(20,2) DEFAULT 0 AFTER `expiry_year`");
            }
            if (!Schema::hasColumn('virtual_cards', 'last4')) {
                DB::statement("ALTER TABLE `virtual_cards` ADD COLUMN `last4` VARCHAR(4) NULL AFTER `card_balance`");
            }
        }

        // Add Sudo dollar card settings to card_settings table using raw SQL
        if (Schema::hasTable('card_settings')) {
            if (!Schema::hasColumn('card_settings', 'sudo_dollar_rate')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_dollar_rate` DECIMAL(10,2) DEFAULT 1500.00");
            }
            if (!Schema::hasColumn('card_settings', 'sudo_creation_fee')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_creation_fee` DECIMAL(10,2) DEFAULT 2.00");
            }
            if (!Schema::hasColumn('card_settings', 'sudo_funding_fee_percent')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_funding_fee_percent` DECIMAL(5,2) DEFAULT 1.50");
            }
            if (!Schema::hasColumn('card_settings', 'sudo_withdrawal_fee_percent')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_withdrawal_fee_percent` DECIMAL(5,2) DEFAULT 1.50");
            }
            if (!Schema::hasColumn('card_settings', 'sudo_card_lock')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_card_lock` TINYINT DEFAULT 0");
            }
            if (!Schema::hasColumn('card_settings', 'sudo_failed_tx_fee')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_failed_tx_fee` DECIMAL(10,2) DEFAULT 0.40");
            }
            if (!Schema::hasColumn('card_settings', 'sudo_max_daily_declines')) {
                DB::statement("ALTER TABLE `card_settings` ADD COLUMN `sudo_max_daily_declines` INT DEFAULT 3");
            }

            // Update defaults for existing row
            DB::table('card_settings')->where('id', 1)->update([
                'sudo_dollar_rate' => 1500.00,
                'sudo_creation_fee' => 2.00,
                'sudo_funding_fee_percent' => 1.50,
                'sudo_withdrawal_fee_percent' => 1.50,
                'sudo_card_lock' => 0,
                'sudo_failed_tx_fee' => 0.40,
                'sudo_max_daily_declines' => 3,
            ]);
        }

        // Add sudo_customer_id to user table
        if (Schema::hasTable('user')) {
            if (!Schema::hasColumn('user', 'sudo_customer_id')) {
                DB::statement("ALTER TABLE `user` ADD COLUMN `sudo_customer_id` VARCHAR(255) NULL");
            }
        }

        // Create sudo_webhooks table for logging
        if (!Schema::hasTable('sudo_webhooks')) {
            Schema::create('sudo_webhooks', function (Blueprint $table) {
                $table->id();
                $table->string('event_type');
                $table->string('card_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->decimal('amount', 20, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->string('status')->default('pending');
                $table->string('merchant_name')->nullable();
                $table->string('merchant_category')->nullable();
                $table->string('channel')->nullable();
                $table->longText('payload')->nullable();
                $table->longText('response')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sudo_webhooks');

        if (Schema::hasTable('user')) {
            if (Schema::hasColumn('user', 'sudo_customer_id')) {
                Schema::table('user', function (Blueprint $table) {
                    $table->dropColumn('sudo_customer_id');
                });
            }
        }
    }
};
