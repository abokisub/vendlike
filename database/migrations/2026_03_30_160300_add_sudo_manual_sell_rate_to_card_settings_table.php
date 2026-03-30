<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSudoManualSellRateToCardSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_settings', function (Blueprint $column) {
            $column->decimal('sudo_manual_sell_rate', 10, 2)->nullable()->after('sudo_dollar_rate');
        });

        // Initialize with the current manual dollar rate
        DB::table('card_settings')->where('id', 1)->update([
            'sudo_manual_sell_rate' => DB::table('card_settings')->where('id', 1)->value('sudo_dollar_rate') ?: 1500
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('card_settings', function (Blueprint $column) {
            $column->dropColumn('sudo_manual_sell_rate');
        });
    }
}
