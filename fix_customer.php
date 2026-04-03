<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check what Xixapay returned for user 20
$u20 = DB::table('user')->where('id', 20)->first();
echo "User 20 xixapay_kyc_data: " . ($u20->xixapay_kyc_data ?? 'NULL') . "\n";
echo "User 20 kyc_documents: " . substr($u20->kyc_documents ?? 'NULL', 0, 200) . "\n";

// Check dollar_customers
$dc = DB::table('dollar_customers')->where('user_id', 20)->first();
echo "dollar_customers for user 20: " . json_encode($dc) . "\n";

// Try to get customer_id from kyc_documents JSON
$kycDocs = json_decode($u20->kyc_documents ?? '{}', true);
$submittedEmail = $kycDocs['submitted_metadata']['email'] ?? $u20->email;

// Call Xixapay to get/create customer for user 20
// We'll use the stored BVN from user table
$bvn = $u20->bvn ?? null;
echo "User 20 BVN: " . ($bvn ?? 'NULL') . "\n";
echo "User 20 email: " . $u20->email . "\n";

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
