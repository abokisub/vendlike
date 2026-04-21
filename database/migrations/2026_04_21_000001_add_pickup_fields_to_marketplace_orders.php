<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add pickup address fields to marketplace_orders
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_name VARCHAR(200) NULL AFTER delivery_eta");
        } catch (\Exception $e) {}
        
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_phone VARCHAR(20) NULL AFTER pickup_name");
        } catch (\Exception $e) {}
        
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_address TEXT NULL AFTER pickup_phone");
        } catch (\Exception $e) {}
        
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_city VARCHAR(100) NULL AFTER pickup_address");
        } catch (\Exception $e) {}
        
        try {
            DB::statement("ALTER TABLE marketplace_orders ADD COLUMN pickup_state VARCHAR(100) NULL AFTER pickup_city");
        } catch (\Exception $e) {}
    }

    public function down()
    {
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_name"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_phone"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_address"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_city"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE marketplace_orders DROP COLUMN pickup_state"); } catch (\Exception $e) {}
    }
};
