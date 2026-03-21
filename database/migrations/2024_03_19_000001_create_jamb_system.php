<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create jamb_purchases table
        if (!Schema::hasTable('jamb_purchases')) {
            DB::statement("
                CREATE TABLE jamb_purchases (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL,
                    profile_id VARCHAR(50) NOT NULL,
                    customer_name VARCHAR(200) NULL,
                    variation_code VARCHAR(50) NOT NULL,
                    variation_name VARCHAR(200) NULL,
                    phone VARCHAR(20) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    purchased_code TEXT NULL,
                    oldbal DECIMAL(12,2) NOT NULL DEFAULT 0,
                    newbal DECIMAL(12,2) NOT NULL DEFAULT 0,
                    transid VARCHAR(100) NOT NULL,
                    plan_status INT NOT NULL DEFAULT 0,
                    plan_date TIMESTAMP NULL,
                    api_response TEXT NULL,
                    request_id VARCHAR(100) NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_jamb_username (username),
                    INDEX idx_jamb_transid (transid),
                    INDEX idx_jamb_status (plan_status),
                    INDEX idx_jamb_profile (profile_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Add jamb_status to settings (1 = enabled, 0 = disabled)
        if (Schema::hasTable('settings')) {
            if (!Schema::hasColumn('settings', 'jamb_status')) {
                DB::statement("ALTER TABLE settings ADD COLUMN jamb_status TINYINT NOT NULL DEFAULT 1");
            }
            if (!Schema::hasColumn('settings', 'jamb_provider')) {
                DB::statement("ALTER TABLE settings ADD COLUMN jamb_provider VARCHAR(50) NOT NULL DEFAULT 'vtpass'");
            }
            if (!Schema::hasColumn('settings', 'jamb_discount')) {
                DB::statement("ALTER TABLE settings ADD COLUMN jamb_discount DECIMAL(5,2) NOT NULL DEFAULT 0");
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('jamb_purchases');

        if (Schema::hasTable('settings')) {
            if (Schema::hasColumn('settings', 'jamb_status')) {
                DB::statement("ALTER TABLE settings DROP COLUMN jamb_status");
            }
            if (Schema::hasColumn('settings', 'jamb_provider')) {
                DB::statement("ALTER TABLE settings DROP COLUMN jamb_provider");
            }
            if (Schema::hasColumn('settings', 'jamb_discount')) {
                DB::statement("ALTER TABLE settings DROP COLUMN jamb_discount");
            }
        }
    }
};
