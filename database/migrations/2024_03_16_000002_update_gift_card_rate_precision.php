<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rate is now ₦ per $1 (e.g., 650 = ₦650/$1), needs larger precision
        DB::statement('ALTER TABLE gift_card_types MODIFY rate DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE gift_card_types MODIFY previous_rate DECIMAL(10,2) NULL');
        DB::statement('ALTER TABLE gift_card_types MODIFY rate_change DECIMAL(10,2) NOT NULL DEFAULT 0.00');

        // Also update country_rate in pivot table
        DB::statement('ALTER TABLE gift_card_countries MODIFY country_rate DECIMAL(10,2) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gift_card_types MODIFY rate DECIMAL(5,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE gift_card_types MODIFY previous_rate DECIMAL(5,2) NULL');
        DB::statement('ALTER TABLE gift_card_types MODIFY rate_change DECIMAL(5,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE gift_card_countries MODIFY country_rate DECIMAL(5,2) NULL');
    }
};
