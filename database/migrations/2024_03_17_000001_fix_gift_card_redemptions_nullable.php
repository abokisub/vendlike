<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE gift_card_redemptions MODIFY image_path VARCHAR(500) NULL');
        DB::statement('ALTER TABLE gift_card_redemptions MODIFY card_code VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gift_card_redemptions MODIFY image_path VARCHAR(500) NOT NULL');
        DB::statement('ALTER TABLE gift_card_redemptions MODIFY card_code VARCHAR(255) NOT NULL');
    }
};
