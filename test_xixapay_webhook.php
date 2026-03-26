<?php
/**
 * Xixapay Webhook Signature Test - Run on server: php test_xixapay_webhook.php
 * Auto-deletes after running
 */

// Read key directly from .env
$envPath = __DIR__ . '/.env';
$secretKey = '';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, 'XIXAPAY_SECRET_KEY=') === 0) {
            $secretKey = trim(substr($line, strlen('XIXAPAY_SECRET_KEY=')), '"\'');
            break;
        }
    }
}

echo "Key length: " . strlen($secretKey) . " chars" . PHP_EOL;
echo "Key preview: " . substr($secretKey, 0, 20) . "..." . PHP_EOL . PHP_EOL;

// Real payload from log (exact 612 bytes)
$realPayload = '{"notification_status":"payment_successful","transaction_id":"33ce0130cc8e4cfdc6d5426e5b33039b8dd343ac","amount_paid":100,"settlement_amount":99,"settlement_fee":1,"transaction_status":"success","externalReference":null,"sender":{"name":"ABOKI TELECOMMUNICATION SERVICES","account_number":"7040540018","bank":"OPAY"},"receiver":{"name":"ERCAS-XIXAPAY\/Vendlike-Jam","account_number":"0993481211","bank":"kolomoni"},"customer":{"name":"Jami","email":"jami@gmail.com","phone":null,"customer_id":"c8fb26d4a0c5e0df0fc63ed1f2a617ff5bbf04ce"},"description":"Your payment has been successfully processed.","timestamp":"2026-03-26T07:15:56.000000Z"}';
$realSig = '153019ee8f4138889a04b78e86c391ca5bde70a3378a7d7b44ab19c03f1cf359';

$computed = hash_hmac('sha256', $realPayload, $secretKey);
echo "Real payload test:" . PHP_EOL;
echo "  Received:  $realSig" . PHP_EOL;
echo "  Computed:  $computed" . PHP_EOL;
echo "  Match: " . (hash_equals($computed, $realSig) ? "✅ YES - KEY IS CORRECT!" : "❌ NO") . PHP_EOL . PHP_EOL;

// Send live test
$testPayload = json_encode([
    "notification_status" => "payment_successful",
    "transaction_id" => "TEST_" . time(),
    "amount_paid" => 100,
    "settlement_amount" => 99,
    "settlement_fee" => 1,
    "transaction_status" => "success",
    "externalReference" => null,
    "sender" => ["name" => "TEST", "account_number" => "0000000000", "bank" => "TEST"],
    "receiver" => ["name" => "TEST", "account_number" => "0993481211", "bank" => "kolomoni"],
    "customer" => ["name" => "Jami", "email" => "jami@gmail.com", "phone" => null, "customer_id" => "test"],
    "description" => "Test",
    "timestamp" => date('c')
]);
$testSig = hash_hmac('sha256', $testPayload, $secretKey);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://app.vendlike.com/api/xixapay_webhook/secure/callback/pay/habukhan/0001',
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => $testPayload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'xixapay: ' . $testSig],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Live endpoint test: HTTP $code - $resp" . PHP_EOL;
echo ($code === 200 ? "✅ WEBHOOK WORKING!" : "❌ Still failing") . PHP_EOL;

unlink(__FILE__);
echo "Script deleted." . PHP_EOL;
