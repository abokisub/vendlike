<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$u = DB::table('user')->where('id', 20)->first();
if ($u && $u->customer_id) {
    $p = explode(' ', $u->name);
    DB::table('dollar_customers')->updateOrInsert(
        ['user_id' => $u->id, 'provider' => 'xixapay'],
        [
            'customer_id' => $u->customer_id,
            'first_name' => $p[0] ?? '',
            'last_name' => implode(' ', array_slice($p, 1)) ?: ($p[0] ?? ''),
            'email' => $u->email,
            'phone' => $u->username,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]
    );
    echo "Done: " . $u->customer_id . "\n";
} else {
    echo "User 20 not found or no customer_id\n";
    if ($u) echo "customer_id value: " . var_export($u->customer_id, true) . "\n";
}
