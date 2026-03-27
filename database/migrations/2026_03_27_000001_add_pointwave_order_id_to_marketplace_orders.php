<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPointwaveOrderIdToMarketplaceOrders extends Migration
{
    public function up()
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->string('pointwave_order_id')->nullable()->after('monnify_reference');
        });
    }

    public function down()
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn('pointwave_order_id');
        });
    }
}
