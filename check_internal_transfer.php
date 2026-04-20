<?php
// Quick script to check internal_transfer_enabled setting
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check the database value
$setting = DB::table('settings')->select('internal_transfer_enabled')->first();

echo "=== INTERNAL TRANSFER SETTING CHECK ===\n";
echo "internal_transfer_enabled value: " . var_export($setting->internal_transfer_enabled ?? 'NOT FOUND', true) . "\n";
echo "Type: " . gettype($setting->internal_transfer_enabled ?? null) . "\n";
echo "\n";

// Check if column exists
try {
    $columns = DB::select("SHOW COLUMNS FROM settings LIKE 'internal_transfer_enabled'");
    if (empty($columns)) {
        echo "❌ Column 'internal_transfer_enabled' does NOT exist in settings table!\n";
        echo "You need to run the migration!\n";
    } else {
        echo "✅ Column exists\n";
        echo "Column details: " . json_encode($columns, JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Exception $e) {
    echo "Error checking column: " . $e->getMessage() . "\n";
}

echo "\n=== FULL SETTINGS ROW ===\n";
$fullSettings = DB::table('settings')->first();
echo json_encode($fullSettings, JSON_PRETTY_PRINT) . "\n";
