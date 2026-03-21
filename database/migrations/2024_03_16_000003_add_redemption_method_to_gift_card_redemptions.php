<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('gift_card_redemptions', 'redemption_method')) {
            Schema::table('gift_card_redemptions', function (Blueprint $table) {
                $table->enum('redemption_method', ['physical', 'code'])->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gift_card_redemptions', 'redemption_method')) {
            Schema::table('gift_card_redemptions', function (Blueprint $table) {
                $table->dropColumn('redemption_method');
            });
        }
    }
};
