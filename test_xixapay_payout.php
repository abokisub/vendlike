<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$xixa = config('services.xixapay');
$headers = [
    'Authorization' => 'Bearer ' . str_replace('Bearer ', '', $xixa['authorization']),
    'api-key' => $xixa['api_key'],
    'Content-Type' => 'application/json',
];

// 1. Check balance
echo "=== BALANCE CHECK ===" . PHP_EOL;
$r = Illuminate\Support\Facades\Http::timeout(30)->withHeaders($headers)
    ->get('https://api.xixapay.com/api/get/balance');
echo "HTTP: " . $r->status() . PHP_EOL;
echo json_encode($r->json(), JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;

// 2. Test payout to a real account (small amount)
echo "=== PAYOUT TEST (₦100 to OPay 7040540018) ===" . PHP_EOL;
$r2 = Illuminate\Support\Facades\Http::timeout(60)->withHeaders($headers)
    ->post('https://api.xixapay.com/api/v1/transfer', [
        'businessId' => $xixa['business_id'],
        'amount' => 100,
        'bank' => '100004', // OPay bank code
        'accountNumber' => '7040540018',
        'narration' => 'VendLike Test Payout',
    ]);
echo "HTTP: " . $r2->status() . PHP_EOL;
echo json_encode($r2->json(), JSON_PRETTY_PRINT) . PHP_EOL;

unlink(__FILE__);
echo PHP_EOL . "Script deleted." . PHP_EOL;
