<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::table('card_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('card_settings', 'dollar_card_provider')) {
                $table->string('dollar_card_provider')->default('sudo')->after('id');
            }
            if (!Schema::hasColumn('card_settings', 'card_lock')) {
                $table->boolean('card_lock')->default(false)->after('dollar_card_provider');
            }
            if (!Schema::hasColumn('card_settings', 'sudo_card_lock')) {
                $table->boolean('sudo_card_lock')->default(false);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_dollar_rate')) {
                $table->decimal('sudo_dollar_rate', 10, 2)->default(1500.00);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_manual_sell_rate')) {
                $table->decimal('sudo_manual_sell_rate', 10, 2)->default(1500.00);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_rate_source')) {
                $table->string('sudo_rate_source')->default('manual');
            }
            if (!Schema::hasColumn('card_settings', 'sudo_creation_fee')) {
                $table->decimal('sudo_creation_fee', 10, 2)->default(2.00);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_funding_fee_percent')) {
                $table->decimal('sudo_funding_fee_percent', 5, 2)->default(1.50);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_withdrawal_fee_percent')) {
                $table->decimal('sudo_withdrawal_fee_percent', 5, 2)->default(1.50);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_failed_tx_fee')) {
                $table->decimal('sudo_failed_tx_fee', 10, 2)->default(0.40);
            }
            if (!Schema::hasColumn('card_settings', 'sudo_max_daily_declines')) {
                $table->integer('sudo_max_daily_declines')->default(3);
            }

            // Xixapay Columns
            if (!Schema::hasColumn('card_settings', 'xixapay_manual_buy_rate')) {
                $table->decimal('xixapay_manual_buy_rate', 10, 2)->default(1500.00);
            }
            if (!Schema::hasColumn('card_settings', 'xixapay_manual_sell_rate')) {
                $table->decimal('xixapay_manual_sell_rate', 10, 2)->default(1500.00);
            }
            if (!Schema::hasColumn('card_settings', 'xixapay_funding_fee_percent')) {
                $table->decimal('xixapay_funding_fee_percent', 5, 2)->default(1.50);
            }
            if (!Schema::hasColumn('card_settings', 'xixapay_withdrawal_fee_percent')) {
                $table->decimal('xixapay_withdrawal_fee_percent', 5, 2)->default(1.50);
            }
            if (!Schema::hasColumn('card_settings', 'xixapay_creation_fee')) {
                $table->decimal('xixapay_creation_fee', 10, 2)->default(5.00);
            }
        });

        // Ensure row with ID 1 exists
        if (DB::table('card_settings')->where('id', 1)->count() == 0) {
            DB::table('card_settings')->insert([
                'id' => 1,
                'dollar_card_provider' => 'sudo',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function down()
    {
        Schema::table('card_settings', function (Blueprint $table) {
            $table->dropColumn([
                'dollar_card_provider',
                'card_lock',
                'sudo_card_lock',
                'sudo_dollar_rate',
                'sudo_manual_sell_rate',
                'sudo_rate_source',
                'sudo_creation_fee',
                'sudo_funding_fee_percent',
                'sudo_withdrawal_fee_percent',
                'sudo_failed_tx_fee',
                'sudo_max_daily_declines',
                'xixapay_manual_buy_rate',
                'xixapay_manual_sell_rate',
                'xixapay_funding_fee_percent',
                'xixapay_withdrawal_fee_percent',
                'xixapay_creation_fee'
            ]);
        });
    }
};
