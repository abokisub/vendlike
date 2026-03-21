<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Categories
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // emoji or icon name
            $table->string('image')->nullable(); // category banner image
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Products
        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2); // price in NGN
            $table->decimal('discount_price', 12, 2)->nullable(); // sale price
            $table->integer('stock')->default(0);
            $table->json('images')->nullable(); // array of image paths
            $table->json('sizes')->nullable(); // e.g. ["S","M","L","XL"]
            $table->json('colors')->nullable(); // e.g. ["Red","Blue","Black"]
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('marketplace_categories')->onDelete('cascade');
        });

        // Orders
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('reference')->unique(); // MP_xxxxx
            $table->decimal('total_amount', 12, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('grand_total', 12, 2); // total_amount + delivery_fee
            $table->string('status')->default('pending'); // pending, processing, shipped, delivered, cancelled
            $table->string('delivery_name');
            $table->string('delivery_phone');
            $table->text('delivery_address');
            $table->string('delivery_city')->nullable();
            $table->string('delivery_state')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        // Order Items
        Schema::create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name'); // snapshot
            $table->decimal('unit_price', 12, 2); // snapshot
            $table->integer('quantity')->default(1);
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('marketplace_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('marketplace_products')->onDelete('cascade');
        });

        // Settings
        DB::statement("ALTER TABLE `settings` ADD COLUMN `marketplace_status` TINYINT(1) DEFAULT 1");
        DB::statement("ALTER TABLE `settings` ADD COLUMN `marketplace_delivery_fee` DECIMAL(10,2) DEFAULT 0.00");
        DB::statement("ALTER TABLE `settings` ADD COLUMN `marketplace_delivery_mode` VARCHAR(50) DEFAULT 'self'"); // self or partner
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_order_items');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_categories');
    }
};
