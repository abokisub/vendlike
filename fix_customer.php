<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check what columns exist and what data we have
$cols = DB::select("SHOW COLUMNS FROM `user` LIKE '%customer%'");
echo "Customer columns: " . json_encode(array_column($cols, 'Field')) . "\n";

$cols2 = DB::select("SHOW COLUMNS FROM `user` LIKE '%kyc%'");
echo "KYC columns: " . json_encode(array_column($cols2, 'Field')) . "\n";

// Check user 20 specifically
$u20 = DB::table('user')->where('id', 20)->first();
echo "User 20 customer_id: " . ($u20->customer_id ?? 'NULL') . "\n";
echo "User 20 kyc: " . ($u20->kyc ?? 'NULL') . "\n";
echo "User 20 kyc_status: " . ($u20->kyc_status ?? 'NULL') . "\n";

// Check dollar_customers table
$dcCols = DB::select("SHOW COLUMNS FROM `dollar_customers`");
echo "dollar_customers columns: " . json_encode(array_column($dcCols, 'Field')) . "\n";

$users = DB::table('user')->whereNotNull('customer_id')->where('customer_id', '!=', '')->get();

foreach ($users as $u) {
    $parts = explode(' ', $u->name ?? '');
    $firstName = $parts[0] ?? '';
    $lastName = implode(' ', array_slice($parts, 1)) ?: $firstName;

    DB::table('dollar_customers')->updateOrInsert(
        ['user_id' => $u->id, 'provider' => 'xixapay'],
        [
            'customer_id' => $u->customer_id,
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $u->email,
            'phone'       => $u->username,
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]
    );
    echo "Synced user {$u->id} ({$u->username}) => {$u->customer_id}\n";
}

echo "Done. Total: " . count($users) . "\n";
