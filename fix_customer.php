<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

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
