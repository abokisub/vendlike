<?php
/**
 * Quick email test script — run via: php artisan tinker < test_email.php
 * Tests all email templates: Gift Card, JAMB PIN, Recharge Card PIN, Exam PIN
 */

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

$testEmail = 'support@vendlike.com'; // Send test to self

echo "=== EMAIL DELIVERY TEST ===\n\n";

// ─── TEST 1: Basic SMTP Connection ───
echo "1. Testing SMTP connection...\n";
try {
    Mail::raw('This is a test email from Vendlike. SMTP is working correctly.', function ($message) use ($testEmail) {
        $message->to($testEmail)
            ->subject('✅ Vendlike SMTP Test - ' . now()->format('H:i:s'));
    });
    echo "   ✅ Basic SMTP test sent!\n\n";
} catch (\Exception $e) {
    echo "   ❌ SMTP FAILED: " . $e->getMessage() . "\n\n";
}

// ─── TEST 2: JAMB PIN Email Template ───
echo "2. Testing JAMB PIN email template...\n";
try {
    $jambData = [
        'email' => $testEmail,
        'username' => 'TestUser',
        'title' => 'JAMB PIN - UTME | Vendlike',
        'variation_name' => 'UTME',
        'profile_id' => '1234567890',
        'customer_name' => 'Test Student',
        'phone' => '08012345678',
        'amount' => 5500,
        'purchased_code' => '0293847561029',
        'transid' => 'JAMB_TEST_001',
        'newbal' => 45000,
        'date' => now()->format('d M Y, h:i A'),
    ];
    \App\Http\Controllers\MailController::send_mail($jambData, 'email.jamb_pin');
    echo "   ✅ JAMB PIN email sent!\n\n";
} catch (\Exception $e) {
    echo "   ❌ JAMB EMAIL FAILED: " . $e->getMessage() . "\n\n";
}

// ─── TEST 3: Recharge Card PIN Email Template ───
echo "3. Testing Recharge Card PIN email template...\n";
try {
    $rechargeData = [
        'email' => $testEmail,
        'username' => 'TestUser',
        'title' => 'MTN Recharge Card - Vendlike',
        'network' => 'MTN',
        'card_name' => 'MTN ₦1000 Recharge Card',
        'quantity' => 3,
        'amount' => 2850,
        'transid' => 'Recharge_card_TEST_001',
        'date' => now()->format('d M Y, h:i A'),
        'newbal' => 42150,
        'pins' => [
            ['pin' => '1234567890123456', 'serial' => 'SN001122334455'],
            ['pin' => '6543210987654321', 'serial' => 'SN998877665544'],
            ['pin' => '1122334455667788', 'serial' => 'SN112233445566'],
        ],
        'load_pin' => '*555*PIN#',
        'check_balance' => '*556#',
    ];
    \App\Http\Controllers\MailController::send_mail($rechargeData, 'email.recharge_pin');
    echo "   ✅ Recharge Card PIN email sent!\n\n";
} catch (\Exception $e) {
    echo "   ❌ RECHARGE EMAIL FAILED: " . $e->getMessage() . "\n\n";
}

// ─── TEST 4: Exam PIN Email Template ───
echo "4. Testing Exam PIN email template...\n";
try {
    $examData = [
        'email' => $testEmail,
        'username' => 'TestUser',
        'title' => 'WAEC Exam PIN - Vendlike',
        'exam_name' => 'WAEC',
        'quantity' => 1,
        'amount' => 3500,
        'transid' => 'EXAM_TEST_001',
        'date' => now()->format('d M Y, h:i A'),
        'newbal' => 38650,
        'purchased_code' => 'WRN-293-847-5610',
    ];
    \App\Http\Controllers\MailController::send_mail($examData, 'email.exam_pin');
    echo "   ✅ Exam PIN email sent!\n\n";
} catch (\Exception $e) {
    echo "   ❌ EXAM EMAIL FAILED: " . $e->getMessage() . "\n\n";
}

// ─── TEST 5: Gift Card Email (inline HTML) ───
echo "5. Testing Gift Card purchase email...\n";
try {
    $appName = config('app.name', 'Vendlike');
    $html = '
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#f9fafb;border-radius:12px;">
        <div style="text-align:center;padding:20px 0;">
            <h2 style="color:#1a1a2e;margin:0;">🎁 Gift Card Purchase Successful</h2>
            <p style="color:#6b7280;margin:8px 0 0;">Your gift card is ready!</p>
        </div>
        <div style="background:#fff;border-radius:8px;padding:24px;margin:16px 0;border:1px solid #e5e7eb;">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Product</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">Amazon Gift Card</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Brand</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">Amazon</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Quantity</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">2</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Card Value</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">USD 25.00</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Amount Paid</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:16px;color:#16a34a;">₦42,500.00</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Reference</td><td style="padding:8px 0;text-align:right;font-size:13px;font-family:monospace;">BG_TEST_001</td></tr>
            </table>
        </div>
        <div style="background:#f0fdf4;border-radius:8px;padding:20px;margin:16px 0;border:1px solid #bbf7d0;">
            <h3 style="color:#15803d;margin:0 0 12px;font-size:16px;">🔑 Your Gift Card Details (2 cards)</h3>
            <div>
                <p style="margin:0 0 6px;font-size:13px;color:#166534;font-weight:600;">Card 1</p>
                <p style="margin:6px 0;font-size:14px;"><strong>Card Number:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">AMZN-1234-5678-9012</span></p>
                <p style="margin:6px 0;font-size:14px;"><strong>PIN Code:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">ABC123DEF456</span></p>
            </div>
            <div style="border-top:1px solid #bbf7d0;padding-top:12px;margin-top:12px;">
                <p style="margin:0 0 6px;font-size:13px;color:#166534;font-weight:600;">Card 2</p>
                <p style="margin:6px 0;font-size:14px;"><strong>Card Number:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">AMZN-9876-5432-1098</span></p>
                <p style="margin:6px 0;font-size:14px;"><strong>PIN Code:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">XYZ789GHI012</span></p>
            </div>
        </div>
        <div style="background:#fff;border-radius:8px;padding:16px;margin:16px 0;border:1px solid #e5e7eb;">
            <h4 style="color:#374151;margin:0 0 8px;font-size:14px;">How to Redeem</h4>
            <p style="color:#6b7280;font-size:13px;margin:0;line-height:1.5;">Go to amazon.com/redeem and enter your gift card code.</p>
        </div>
        <div style="text-align:center;padding:16px 0;color:#9ca3af;font-size:12px;">
            <p>Thank you for using ' . $appName . '</p>
            <p>This is an automated email. Please do not reply.</p>
        </div>
    </div>';

    Mail::html($html, function ($message) use ($testEmail, $appName) {
        $message->to($testEmail, 'TestUser')
            ->subject('🎁 Gift Card Purchase - Amazon Gift Card | ' . $appName);
    });
    echo "   ✅ Gift Card email sent!\n\n";
} catch (\Exception $e) {
    echo "   ❌ GIFT CARD EMAIL FAILED: " . $e->getMessage() . "\n\n";
}

echo "=== ALL TESTS COMPLETE ===\n";
echo "Check inbox at: support@vendlike.com\n";
