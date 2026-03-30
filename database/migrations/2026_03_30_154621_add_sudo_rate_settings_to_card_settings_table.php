<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSudoRateSettingsToCardSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('card_settings', function (Blueprint $column) {
            $column->string('sudo_rate_source')->default('manual')->after('sudo_dollar_rate');
            $column->decimal('sudo_auto_buy_rate', 10, 2)->nullable()->after('sudo_rate_source');
            $column->decimal('sudo_auto_sell_rate', 10, 2)->nullable()->after('sudo_auto_buy_rate');
            $column->timestamp('sudo_auto_rate_last_updated')->nullable()->after('sudo_auto_sell_rate');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('card_settings', function (Blueprint $column) {
            $column->dropColumn([
                'sudo_rate_source',
                'sudo_auto_buy_rate',
                'sudo_auto_sell_rate',
                'sudo_auto_rate_last_updated'
            ]);
        });
    }
}
