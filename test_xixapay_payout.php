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

echo "Business ID: " . $xixa['business_id'] . PHP_EOL . PHP_EOL;

// 1. Check balance - try different endpoints
foreach (['https://api.xixapay.com/api/get/balance', 'https://api.xixapay.com/api/v1/balance', 'https://api.xixapay.com/api/wallet/balance'] as $url) {
    $r = Illuminate\Support\Facades\Http::timeout(15)->withHeaders($headers)->get($url);
    echo "Balance $url: HTTP " . $r->status() . " - " . substr($r->body(), 0, 100) . PHP_EOL;
}

echo PHP_EOL;

// 2. Get banks list to find correct OPay bank code
echo "=== BANKS ===" . PHP_EOL;
$r = Illuminate\Support\Facades\Http::timeout(15)->withHeaders($headers)->get('https://api.xixapay.com/api/get/banks');
echo "HTTP: " . $r->status() . PHP_EOL;
$banks = $r->json();
// Find OPay
if (is_array($banks)) {
    foreach ($banks as $b) {
        $name = $b['bank_name'] ?? $b['bankName'] ?? '';
        if (stripos($name, 'opay') !== false || stripos($name, 'palmpay') !== false || stripos($name, 'kolomoni') !== false) {
            echo "  " . $name . " => code: " . ($b['bank_code'] ?? $b['bankCode'] ?? 'N/A') . PHP_EOL;
        }
    }
}

echo PHP_EOL;

// 3. Try transfer with different payload formats
echo "=== TRANSFER TEST ===" . PHP_EOL;

// Format 1: bank as string code
$payload1 = [
    'businessId' => $xixa['business_id'],
    'amount' => 100,
    'bank' => '100004',
    'accountNumber' => '7040540018',
    'narration' => 'Test',
];
$r1 = Illuminate\Support\Facades\Http::timeout(30)->withHeaders($headers)->post('https://api.xixapay.com/api/v1/transfer', $payload1);
echo "Format 1 (bank=100004): HTTP " . $r1->status() . " - " . $r1->body() . PHP_EOL . PHP_EOL;

// Format 2: bank_code instead of bank
$payload2 = [
    'businessId' => $xixa['business_id'],
    'amount' => 100,
    'bank_code' => '100004',
    'account_number' => '7040540018',
    'narration' => 'Test',
];
$r2 = Illuminate\Support\Facades\Http::timeout(30)->withHeaders($headers)->post('https://api.xixapay.com/api/v1/transfer', $payload2);
echo "Format 2 (bank_code): HTTP " . $r2->status() . " - " . $r2->body() . PHP_EOL;

unlink(__FILE__);
echo PHP_EOL . "Script deleted." . PHP_EOL;
