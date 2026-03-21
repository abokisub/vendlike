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
        // Remove duplicate countries, keeping the first occurrence of each country code
        DB::statement("
            DELETE c1 FROM countries c1
            INNER JOIN countries c2 
            WHERE c1.id > c2.id 
            AND c1.code = c2.code
        ");
        
        // Add unique constraint to prevent future duplicates
        Schema::table('countries', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }
};