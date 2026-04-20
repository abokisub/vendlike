<?php
// Script to add internal_transfer_enabled column if missing and set it to disabled
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== FIXING INTERNAL TRANSFER SETTING ===\n\n";

try {
    // Check if column exists
    $columns = DB::select("SHOW COLUMNS FROM settings LIKE 'internal_transfer_enabled'");
    
    if (empty($columns)) {
        echo "❌ Column 'internal_transfer_enabled' does NOT exist!\n";
        echo "Adding column now...\n";
        
        // Add the column
        DB::statement("ALTER TABLE settings ADD COLUMN internal_transfer_enabled TINYINT(1) DEFAULT 1 AFTER transfer_charge_cap");
        
        echo "✅ Column added successfully!\n";
    } else {
        echo "✅ Column already exists\n";
    }
    
    // Now set it to disabled (0)
    echo "\nSetting internal_transfer_enabled to 0 (disabled)...\n";
    DB::table('settings')->update(['internal_transfer_enabled' => 0]);
    
    echo "✅ Internal transfer is now DISABLED\n\n";
    
    // Verify
    $setting = DB::table('settings')->select('internal_transfer_enabled')->first();
    echo "Current value: " . var_export($setting->internal_transfer_enabled, true) . "\n";
    
    if ($setting->internal_transfer_enabled == 0 || $setting->internal_transfer_enabled === '0') {
        echo "✅ SUCCESS! Internal transfer is disabled.\n";
    } else {
        echo "⚠️ WARNING: Value is not 0, it's: " . var_export($setting->internal_transfer_enabled, true) . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
