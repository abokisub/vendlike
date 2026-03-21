<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_types', function (Blueprint $table) {
            $table->enum('speed', ['fast', 'slow'])->default('fast')->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_types', function (Blueprint $table) {
            $table->dropColumn('speed');
        });
    }
};
