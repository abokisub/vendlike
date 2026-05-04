<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe fix for personal_access_tokens table.
 *
 * Problem: The `id` column is missing AUTO_INCREMENT, so MySQL throws:
 *   "Field 'id' doesn't have a default value"
 *
 * This migration fixes it WITHOUT dropping the table or losing any data.
 * Safe for both local development and production.
 */
class FixPersonalAccessTokensIdAutoincrement extends Migration
{
    public function up()
    {
        // Only run if the table exists
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        // Check if id column already has AUTO_INCREMENT — skip if already correct
        $result = DB::select("
            SELECT EXTRA
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'personal_access_tokens'
              AND COLUMN_NAME = 'id'
        ");

        if (!empty($result) && str_contains(strtolower($result[0]->EXTRA), 'auto_increment')) {
            // Already correct, nothing to do
            return;
        }

        // Step 1: Drop primary key if it exists (required before modifying)
        $hasPrimaryKey = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'personal_access_tokens'
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ");

        if (!empty($hasPrimaryKey) && $hasPrimaryKey[0]->cnt > 0) {
            DB::statement('ALTER TABLE personal_access_tokens DROP PRIMARY KEY');
        }

        // Step 2: Set id to BIGINT UNSIGNED NOT NULL AUTO_INCREMENT with PRIMARY KEY
        // This preserves all existing rows and data
        DB::statement('
            ALTER TABLE personal_access_tokens
            MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
        ');
    }

    public function down()
    {
        // Reversing this would break things, so we leave it as-is on rollback
        // AUTO_INCREMENT on a primary key is always the correct state
    }
}
