<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE conversion_wallet_transactions MODIFY COLUMN source_type ENUM('airtime_conversion','gift_card_sale','withdrawal','adjustment','bank_transfer','refund') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE conversion_wallet_transactions MODIFY COLUMN source_type ENUM('airtime_conversion','gift_card_sale','withdrawal','adjustment') NOT NULL");
    }
};
