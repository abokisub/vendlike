<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Multi-file upload support for gift card redemptions.
     * The `additional_images` column already exists (TEXT, nullable).
     * We just ensure image_path is nullable (already done in prior migration).
     * No schema changes needed — we'll store JSON array of paths in additional_images.
     */
    public function up(): void
    {
        // Migrate any existing single image_path data into additional_images JSON format
        // so the new code can read from additional_images consistently
        DB::statement("
            UPDATE gift_card_redemptions 
            SET additional_images = CONCAT('[\"', image_path, '\"]') 
            WHERE image_path IS NOT NULL 
            AND image_path != '' 
            AND (additional_images IS NULL OR additional_images = '')
        ");
    }

    public function down(): void
    {
        // No destructive rollback needed
    }
};
