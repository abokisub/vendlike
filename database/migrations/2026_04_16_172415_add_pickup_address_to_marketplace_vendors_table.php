<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPickupAddressToMarketplaceVendorsTable extends Migration
{
    public function up()
    {
        Schema::table('marketplace_vendors', function (Blueprint $table) {
            $table->text('pickup_address')->nullable()->after('description');
            $table->string('pickup_city')->nullable()->after('pickup_address');
            $table->string('pickup_state')->nullable()->after('pickup_city');
            $table->string('pickup_phone')->nullable()->after('pickup_state');
        });
    }

    public function down()
    {
        Schema::table('marketplace_vendors', function (Blueprint $table) {
            $table->dropColumn(['pickup_address', 'pickup_city', 'pickup_state', 'pickup_phone']);
        });
    }
}
