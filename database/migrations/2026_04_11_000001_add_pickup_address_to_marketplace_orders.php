<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPickupAddressToMarketplaceOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add pickup/sender address columns to marketplace_orders
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_name VARCHAR(255) NULL AFTER delivery_eta");
        } catch (\Exception $e) {
            \Log::info('Column pickup_name may already exist');
        }

        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_address TEXT NULL AFTER pickup_name");
        } catch (\Exception $e) {
            \Log::info('Column pickup_address may already exist');
        }

        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_phone VARCHAR(20) NULL AFTER pickup_address");
        } catch (\Exception $e) {
            \Log::info('Column pickup_phone may already exist');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        try {
            DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_name");
        } catch (\Exception $e) {}

        try {
            DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_address");
        } catch (\Exception $e) {}

        try {
            DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_phone");
        } catch (\Exception $e) {}
    }
}
