<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('settings', 'sell_giftcard_lock')) {
            DB::statement("ALTER TABLE settings ADD COLUMN sell_giftcard_lock TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=unlocked, 1=locked'");
        }
        if (!Schema::hasColumn('settings', 'buy_giftcard_lock')) {
            DB::statement("ALTER TABLE settings ADD COLUMN buy_giftcard_lock TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=unlocked, 1=locked'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settings', 'sell_giftcard_lock')) {
            DB::statement("ALTER TABLE settings DROP COLUMN sell_giftcard_lock");
        }
        if (Schema::hasColumn('settings', 'buy_giftcard_lock')) {
            DB::statement("ALTER TABLE settings DROP COLUMN buy_giftcard_lock");
        }
    }
};
