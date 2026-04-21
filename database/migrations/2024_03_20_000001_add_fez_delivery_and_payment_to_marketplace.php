<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add weight to marketplace_products
        try {
            DB::statement("ALTER TABLE marketplace_products ADD COLUMN weight DECIMAL(8,2) DEFAULT 1.00 AFTER stock");
        } catch (\Exception $e) {}

        // Add Fez delivery + Monnify payment columns to marketplace_orders
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN fez_order_no VARCHAR(50) NULL AFTER tracking_number");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN delivery_status VARCHAR(50) DEFAULT 'pending' AFTER fez_order_no");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN delivery_eta VARCHAR(100) NULL AFTER delivery_status");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'xixapay' AFTER admin_note");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN payment_reference VARCHAR(100) NULL AFTER payment_method");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN monnify_reference VARCHAR(100) NULL AFTER payment_reference");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending' AFTER monnify_reference");
        } catch (\Exception $e) {}
    }

    public function down()
    {
        try { DB::statement("ALTER TABLE marketplace_products DROP COLUMN weight"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN fez_order_no"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN delivery_status"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN delivery_eta"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN payment_method"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN payment_reference"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN monnify_reference"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN payment_status"); } catch (\Exception $e) {}
    }
};
