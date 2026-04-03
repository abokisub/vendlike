<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$u = DB::table('user')->where('id', 20)->first();
echo "User: " . $u->username . "\n";
echo "customer_id: " . var_export($u->customer_id, true) . "\n";
echo "kyc: " . $u->kyc . "\n";
echo "kyc_status: " . ($u->kyc_status ?? 'N/A') . "\n";

// Check if customer exists on Xixapay by looking at kyc_documents
echo "kyc_documents: " . ($u->kyc_documents ?? 'NULL') . "\n";

// Check dollar_customers table
$dc = DB::table('dollar_customers')->where('user_id', 20)->get();
echo "dollar_customers rows: " . $dc->count() . "\n";
foreach ($dc as $row) {
    echo "  - provider: {$row->provider}, customer_id: {$row->customer_id}\n";
}
