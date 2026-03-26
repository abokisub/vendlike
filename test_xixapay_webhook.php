<?php
/**
 * Xixapay Webhook Signature Test
 * Run on server: php test_xixapay_webhook.php
 * Auto-deletes itself after running
 */

// The exact payload from the real webhook log (payload_length: 612)
$realPayload = '{"notification_status":"payment_successful","transaction_id":"33ce0130cc8e4cfdc6d5426e5b33039b8dd343ac","amount_paid":100,"settlement_amount":99,"settlement_fee":1,"transaction_status":"success","externalReference":null,"sender":{"name":"ABOKI TELECOMMUNICATION SERVICES","account_number":"7040540018","bank":"OPAY"},"receiver":{"name":"ERCAS-XIXAPAY\/Vendlike-Jam","account_number":"0993481211","bank":"kolomoni"},"customer":{"name":"Jami","email":"jami@gmail.com","phone":null,"customer_id":"c8fb26d4a0c5e0df0fc63ed1f2a617ff5bbf04ce"},"description":"Your payment has been successfully processed.","timestamp":"2026-03-26T07:15:56.000000Z"}';

// The signature Xixapay sent
$receivedSignature = '153019ee8f4138889a04b78e86c391ca5bde70a3378a7d7b44ab19c03f1cf359';

// Load the secret key from .env
$envFile = __DIR__ . '/.env';
$secretKey = '';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'XIXAPAY_SECRET_KEY=') === 0) {
            $secretKey = trim(substr($line, strlen('XIXAPAY_SECRET_KEY=')));
            break;
        }
    }
}

echo "=== Xixapay Webhook Signature Test ===" . PHP_EOL;
echo "Secret Key (first 20 chars): " . substr($secretKey, 0, 20) . "..." . PHP_EOL;
echo "Secret Key length: " . strlen($secretKey) . PHP_EOL;
echo "Payload length: " . strlen($realPayload) . PHP_EOL . PHP_EOL;

// Test 1: Exact payload
$computed = hash_hmac('sha256', $realPayload, $secretKey);
echo "Test 1 - Exact payload:" . PHP_EOL;
echo "  Received:  " . $receivedSignature . PHP_EOL;
echo "  Computed:  " . $computed . PHP_EOL;
echo "  Match: " . (hash_equals($computed, $receivedSignature) ? "✅ YES" : "❌ NO") . PHP_EOL . PHP_EOL;

// Test 2: Simulate what our webhook handler does
$computed2 = hash_hmac('sha256', $realPayload, $secretKey);
echo "Test 2 - hash_equals check:" . PHP_EOL;
echo "  Match: " . (hash_equals($computed2, $receivedSignature) ? "✅ YES" : "❌ NO") . PHP_EOL . PHP_EOL;

// Test 3: Try with different payload variations
$payloadNoSlash = str_replace('\\/', '/', $realPayload);
$computed3 = hash_hmac('sha256', $payloadNoSlash, $secretKey);
echo "Test 3 - Payload with unescaped slashes:" . PHP_EOL;
echo "  Match: " . (hash_equals($computed3, $receivedSignature) ? "✅ YES" : "❌ NO") . PHP_EOL . PHP_EOL;

// Test 4: Simulate sending a real webhook to our endpoint
echo "Test 4 - Simulating real webhook POST to our endpoint..." . PHP_EOL;
$testPayload = json_encode([
    "notification_status" => "payment_successful",
    "transaction_id" => "TEST_" . time(),
    "amount_paid" => 100,
    "settlement_amount" => 99,
    "settlement_fee" => 1,
    "transaction_status" => "success",
    "externalReference" => null,
    "sender" => ["name" => "TEST SENDER", "account_number" => "0000000000", "bank" => "TEST"],
    "receiver" => ["name" => "TEST RECEIVER", "account_number" => "0993481211", "bank" => "kolomoni"],
    "customer" => ["name" => "Jami", "email" => "jami@gmail.com", "phone" => null, "customer_id" => "test"],
    "description" => "Test payment",
    "timestamp" => date('c')
]);

$testSig = hash_hmac('sha256', $testPayload, $secretKey);
echo "  Test payload signature: " . $testSig . PHP_EOL;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://app.vendlike.com/api/xixapay_webhook/secure/callback/pay/habukhan/0001',
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => $testPayload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'xixapay: ' . $testSig,
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  HTTP Response: " . $httpCode . PHP_EOL;
echo "  Body: " . $response . PHP_EOL;

if ($httpCode === 200) {
    echo PHP_EOL . "✅ WEBHOOK IS WORKING! Signature verification passed." . PHP_EOL;
} else {
    echo PHP_EOL . "❌ Webhook still failing. HTTP: " . $httpCode . PHP_EOL;
}

// Self-delete
unlink(__FILE__);
echo PHP_EOL . "Script deleted." . PHP_EOL;
