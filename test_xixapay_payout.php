<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$xixa = config('services.xixapay');
$secret = str_replace('Bearer ', '', $xixa['authorization']);
$headers = [
    'Authorization' => 'Bearer ' . $secret,
    'api-key' => $xixa['api_key'],
    'Content-Type' => 'application/json',
];

// First verify the account
echo "=== VERIFY ACCOUNT ===" . PHP_EOL;
$verify = Illuminate\Support\Facades\Http::timeout(30)->withHeaders($headers)
    ->post('https://api.xixapay.com/api/verify/bank', [
        'bank' => '100004',
        'accountNumber' => '7040540018',
    ]);
echo "HTTP: " . $verify->status() . " - " . $verify->body() . PHP_EOL . PHP_EOL;

// Test with exact 10-digit OPay number
echo "=== TRANSFER (OPay 7040540018) ===" . PHP_EOL;
$r1 = Illuminate\Support\Facades\Http::timeout(60)->withHeaders($headers)
    ->post('https://api.xixapay.com/api/v1/transfer', [
        'businessId' => $xixa['business_id'],
        'amount' => 100,
        'bank' => '100004',
        'accountNumber' => '7040540018',
        'narration' => 'VendLike Test',
    ]);
echo "HTTP: " . $r1->status() . " - " . $r1->body() . PHP_EOL . PHP_EOL;

// Test with Kolomoni (our own account)
echo "=== TRANSFER (Kolomoni 0993481211) ===" . PHP_EOL;
$r2 = Illuminate\Support\Facades\Http::timeout(60)->withHeaders($headers)
    ->post('https://api.xixapay.com/api/v1/transfer', [
        'businessId' => $xixa['business_id'],
        'amount' => 100,
        'bank' => '090480',
        'accountNumber' => '0993481211',
        'narration' => 'VendLike Test',
    ]);
echo "HTTP: " . $r2->status() . " - " . $r2->body() . PHP_EOL;

unlink(__FILE__);
echo PHP_EOL . "Script deleted." . PHP_EOL;
