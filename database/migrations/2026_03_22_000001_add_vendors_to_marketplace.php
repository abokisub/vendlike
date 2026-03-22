<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('marketplace_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->after('category_id');
            $table->foreign('vendor_id')->references('id')->on('marketplace_vendors')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
        Schema::dropIfExists('marketplace_vendors');
    }
};
