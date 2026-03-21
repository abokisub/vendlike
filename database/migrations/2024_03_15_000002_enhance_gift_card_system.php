<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new columns to gift_card_types
        Schema::table('gift_card_types', function (Blueprint $table) {
            $table->string('logo_path', 500)->nullable()->after('icon');
            $table->decimal('previous_rate', 5, 2)->nullable()->after('rate');
            $table->decimal('rate_change', 5, 2)->default(0.00)->after('previous_rate');
            $table->enum('rate_trend', ['up', 'down', 'stable'])->default('stable')->after('rate_change');
            $table->json('supported_countries')->nullable()->after('description');
            $table->enum('redemption_type', ['both', 'physical', 'code'])->default('both')->after('supported_countries');
            $table->boolean('require_code_for_physical')->default(false)->after('redemption_type');
            $table->integer('sort_order')->default(0)->after('require_code_for_physical');
        });

        // Create countries table
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 3); // US, UK, CA, etc.
            $table->string('flag_emoji', 10)->nullable();
            $table->string('flag_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Create gift_card_countries pivot table
        Schema::create('gift_card_countries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gift_card_type_id');
            $table->unsignedBigInteger('country_id');
            $table->decimal('country_rate', 5, 2)->nullable(); // Different rate per country
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('gift_card_type_id');
            $table->index('country_id');
            $table->unique(['gift_card_type_id', 'country_id']);
        });

        // Enhance redemptions table
        Schema::table('gift_card_redemptions', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('gift_card_type_id');
            $table->enum('redemption_method', ['physical', 'code'])->default('physical')->after('card_code');
            $table->boolean('has_code')->default(false)->after('redemption_method');
            $table->text('additional_images')->nullable()->after('image_path'); // JSON array of additional images
            
            $table->index('country_id');
            $table->index('redemption_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_card_redemptions', function (Blueprint $table) {
            $table->dropColumn(['country_id', 'redemption_method', 'has_code', 'additional_images']);
        });

        Schema::dropIfExists('gift_card_countries');
        Schema::dropIfExists('countries');

        Schema::table('gift_card_types', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path', 'previous_rate', 'rate_change', 'rate_trend',
                'supported_countries', 'redemption_type', 'require_code_for_physical', 'sort_order'
            ]);
        });
    }
};