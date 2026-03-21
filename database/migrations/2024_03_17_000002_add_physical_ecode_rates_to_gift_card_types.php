<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // physical_rate and ecode_rate: the actual conversion rates per type
        // rate remains as the display/general rate users see on the list
        if (!Schema::hasColumn('gift_card_types', 'physical_rate')) {
            DB::statement('ALTER TABLE gift_card_types ADD COLUMN physical_rate DECIMAL(10,2) NULL AFTER rate');
        }
        if (!Schema::hasColumn('gift_card_types', 'ecode_rate')) {
            DB::statement('ALTER TABLE gift_card_types ADD COLUMN ecode_rate DECIMAL(10,2) NULL AFTER physical_rate');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gift_card_types', 'physical_rate')) {
            DB::statement('ALTER TABLE gift_card_types DROP COLUMN physical_rate');
        }
        if (Schema::hasColumn('gift_card_types', 'ecode_rate')) {
            DB::statement('ALTER TABLE gift_card_types DROP COLUMN ecode_rate');
        }
    }
};
