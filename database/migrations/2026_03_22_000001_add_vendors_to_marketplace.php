<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('marketplace_vendors')) {
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
        }

        // Add vendor_id without FK constraint to avoid engine compatibility issues
        if (!Schema::hasColumn('marketplace_products', 'vendor_id')) {
            DB::statement('ALTER TABLE marketplace_products ADD COLUMN vendor_id BIGINT UNSIGNED NULL AFTER category_id');
        }
    }

    public function down()
    {
        if (Schema::hasColumn('marketplace_products', 'vendor_id')) {
            DB::statement('ALTER TABLE marketplace_products DROP COLUMN vendor_id');
        }
        Schema::dropIfExists('marketplace_vendors');
    }
};
