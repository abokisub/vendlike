<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('settings', 'marketplace_payment_provider')) {
            DB::statement("ALTER TABLE settings ADD COLUMN marketplace_payment_provider VARCHAR(20) DEFAULT 'xixapay' AFTER marketplace_delivery_mode");
        }
        DB::table('settings')->whereNull('marketplace_payment_provider')->update(['marketplace_payment_provider' => 'xixapay']);
    }

    public function down()
    {
        if (Schema::hasColumn('settings', 'marketplace_payment_provider')) {
            DB::statement("ALTER TABLE settings DROP COLUMN marketplace_payment_provider");
        }
    }
};
