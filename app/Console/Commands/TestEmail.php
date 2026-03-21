<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Services\InvoiceService;

class TestEmail extends Command
{
    protected $signature = 'test:email';
    protected $description = 'Test all email templates with PDF invoices';

    public function handle()
    {
        $testEmail = 'ajamilubashir@gmail.com';
        $this->info("=== EMAIL + PDF INVOICE TEST ===\n");

        // 1. JAMB PIN
        $this->info("1. JAMB PIN email + PDF...");
        try {
            $data = [
                'email' => $testEmail, 'username' => 'Habukhan',
                'title' => 'JAMB PIN - UTME | Vendlike',
                'variation_name' => 'UTME', 'profile_id' => '1234567890',
                'customer_name' => 'Ajami Lubashir', 'phone' => '08012345678',
                'amount' => 5500, 'purchased_code' => '0293847561029',
                'transid' => 'JAMB_TEST_001', 'newbal' => 45000,
                'date' => now()->format('d M Y, h:i A'),
            ];
            $pdf = InvoiceService::generatePdf('JAMB_PIN', array_merge($data, [
                'invoice_type' => 'JAMB PIN INVOICE', 'reference' => 'JAMB_TEST_001',
                'status' => 'SUCCESSFUL', 'customer_name_student' => 'Ajami Lubashir',
                'customer_email' => $testEmail, 'customer_phone' => '08012345678',
            ]));
            \App\Http\Controllers\MailController::send_mail($data, 'email.jamb_pin', $pdf);
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        // 2. Recharge Card
        $this->info("\n2. Recharge Card PIN email + PDF...");
        try {
            $data = [
                'email' => $testEmail, 'username' => 'Habukhan',
                'title' => 'MTN Recharge Card - Vendlike',
                'network' => 'MTN', 'card_name' => 'MTN ₦1000 Recharge Card',
                'quantity' => 3, 'amount' => 2850,
                'transid' => 'RC_TEST_001', 'date' => now()->format('d M Y, h:i A'),
                'newbal' => 42150,
                'pins' => [
                    ['pin' => '1234567890123456', 'serial' => 'SN001122334455'],
                    ['pin' => '6543210987654321', 'serial' => 'SN998877665544'],
                    ['pin' => '1122334455667788', 'serial' => 'SN112233445566'],
                ],
                'load_pin' => '*555*PIN#', 'check_balance' => '*556#',
            ];
            $pdf = InvoiceService::generatePdf('RECHARGE_CARD', array_merge($data, [
                'invoice_type' => 'RECHARGE CARD INVOICE', 'reference' => 'RC_TEST_001',
                'status' => 'SUCCESSFUL', 'customer_email' => $testEmail,
            ]));
            \App\Http\Controllers\MailController::send_mail($data, 'email.recharge_pin', $pdf);
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        // 3. Exam PIN
        $this->info("\n3. Exam PIN email + PDF...");
        try {
            $data = [
                'email' => $testEmail, 'username' => 'Habukhan',
                'title' => 'WAEC Exam PIN - Vendlike',
                'exam_name' => 'WAEC', 'quantity' => 1, 'amount' => 3500,
                'transid' => 'EXAM_TEST_001', 'date' => now()->format('d M Y, h:i A'),
                'newbal' => 38650, 'purchased_code' => 'WRN-293-847-5610',
            ];
            $pdf = InvoiceService::generatePdf('EXAM_PIN', array_merge($data, [
                'invoice_type' => 'EXAM PIN INVOICE', 'reference' => 'EXAM_TEST_001',
                'status' => 'SUCCESSFUL', 'customer_email' => $testEmail,
            ]));
            \App\Http\Controllers\MailController::send_mail($data, 'email.exam_pin', $pdf);
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        // 4. Gift Card
        $this->info("\n4. Gift Card email + PDF...");
        try {
            $appName = config('app.name', 'Vendlike');
            $cards = [
                ['cardNumber' => 'AMZN-1234-5678-9012', 'pinCode' => 'ABC123DEF456', 'redemptionUrl' => 'https://amazon.com/redeem'],
                ['cardNumber' => 'AMZN-9876-5432-1098', 'pinCode' => 'XYZ789GHI012', 'redemptionUrl' => 'https://amazon.com/redeem'],
            ];

            $pdfAttachment = InvoiceService::generatePdf('GIFT_CARD', [
                'invoice_type' => 'GIFT CARD INVOICE', 'reference' => 'BG_TEST_001',
                'status' => 'SUCCESSFUL', 'customer_name' => 'Habukhan',
                'customer_email' => $testEmail, 'username' => 'Habukhan',
                'date' => now()->format('d M Y, h:i A'),
                'product_name' => 'Amazon Gift Card', 'brand_name' => 'Amazon',
                'quantity' => 2, 'unit_price' => 25, 'currency' => 'USD',
                'naira_amount' => 42500, 'cards' => $cards,
                'redeem_instructions' => 'Go to amazon.com/redeem and enter your gift card code.',
            ]);

            // Build card details HTML
            $cardsHtml = '';
            foreach ($cards as $idx => $card) {
                $cardsHtml .= '<div style="' . ($idx > 0 ? 'border-top:1px solid #bbf7d0;padding-top:12px;margin-top:12px;' : '') . '">'
                    . '<p style="margin:0 0 6px;font-size:13px;color:#166534;font-weight:600;">Card ' . ($idx + 1) . '</p>'
                    . '<p style="margin:6px 0;font-size:14px;"><strong>Card Number:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">' . $card['cardNumber'] . '</span></p>'
                    . '<p style="margin:6px 0;font-size:14px;"><strong>PIN Code:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">' . $card['pinCode'] . '</span></p>'
                    . '</div>';
            }

            $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#f9fafb;border-radius:12px;">'
                . '<div style="text-align:center;padding:20px 0;"><h2 style="color:#1a1a2e;margin:0;">🎁 Gift Card Purchase Successful</h2><p style="color:#6b7280;margin:8px 0 0;">Your gift card is ready!</p></div>'
                . '<div style="background:#fff;border-radius:8px;padding:24px;margin:16px 0;border:1px solid #e5e7eb;"><table style="width:100%;border-collapse:collapse;">'
                . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Product</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">Amazon Gift Card</td></tr>'
                . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Quantity</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">2</td></tr>'
                . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Card Value</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">USD 25.00</td></tr>'
                . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Amount Paid</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:16px;color:#16a34a;">₦42,500.00</td></tr>'
                . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Reference</td><td style="padding:8px 0;text-align:right;font-family:monospace;font-size:13px;">BG_TEST_001</td></tr>'
                . '</table></div>'
                . '<div style="background:#f0fdf4;border-radius:8px;padding:20px;margin:16px 0;border:1px solid #bbf7d0;">'
                . '<h3 style="color:#15803d;margin:0 0 12px;font-size:16px;">🔑 Your Gift Card Details (2 cards)</h3>'
                . $cardsHtml . '</div>'
                . '<div style="text-align:center;padding:16px 0;color:#9ca3af;font-size:12px;"><p>Thank you for using ' . $appName . '</p><p style="font-size:11px;">A PDF invoice is attached to this email.</p></div></div>';

            Mail::html($html, function ($message) use ($testEmail, $appName, $pdfAttachment) {
                $message->to($testEmail, 'Habukhan')
                    ->subject('🎁 Gift Card Purchase - Amazon Gift Card | ' . $appName);
                if ($pdfAttachment) {
                    $message->attachData($pdfAttachment['data'], $pdfAttachment['name'], ['mime' => $pdfAttachment['mime']]);
                }
            });
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        // 5. Order Confirmed
        $this->info("\n5. Order Confirmed email + PDF...");
        try {
            $items = [
                ['name' => 'Nike Air Max 90', 'quantity' => 1, 'size' => '43', 'color' => 'Black', 'subtotal' => 25000],
                ['name' => 'Vendlike Branded T-Shirt', 'quantity' => 2, 'size' => 'L', 'color' => null, 'subtotal' => 10000],
            ];
            $data = [
                'email' => $testEmail, 'username' => 'Habukhan',
                'title' => '🛒 Order Confirmed - MP_TEST_001 | Vendlike',
                'reference' => 'MP_TEST_001', 'total_amount' => 35000,
                'delivery_fee' => 2500, 'grand_total' => 37500,
                'date' => now()->format('d M Y, h:i A'), 'items' => $items,
                'delivery_name' => 'Ajami Lubashir', 'delivery_phone' => '08012345678',
                'delivery_address' => '123 Test Street, Lekki Phase 1',
                'delivery_state' => 'Lagos', 'delivery_eta' => '3-5 business days',
            ];
            $pdf = InvoiceService::generatePdf('MARKETPLACE', array_merge($data, [
                'invoice_type' => 'ORDER INVOICE', 'status' => 'CONFIRMED',
                'customer_name' => 'Habukhan', 'customer_email' => $testEmail,
                'customer_phone' => '08012345678',
            ]));
            \App\Http\Controllers\MailController::send_mail($data, 'email.order_confirmed', $pdf);
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        // 6. Order Shipped
        $this->info("\n6. Order Shipped email + PDF...");
        try {
            $data = [
                'email' => $testEmail, 'username' => 'Habukhan',
                'title' => '🚚 Order Shipped - MP_TEST_001 | Vendlike',
                'reference' => 'MP_TEST_001', 'grand_total' => 37500,
                'tracking_number' => 'FEZ-2026032001234',
                'items' => [['name' => 'Nike Air Max 90', 'quantity' => 1], ['name' => 'Vendlike Branded T-Shirt', 'quantity' => 2]],
                'delivery_name' => 'Ajami Lubashir', 'delivery_phone' => '08012345678',
                'delivery_address' => '123 Test Street, Lekki Phase 1', 'delivery_state' => 'Lagos',
            ];
            $pdf = InvoiceService::generatePdf('MARKETPLACE', array_merge($data, [
                'invoice_type' => 'SHIPPING NOTICE', 'status' => 'SHIPPED',
                'customer_name' => 'Habukhan', 'customer_email' => $testEmail,
                'total_amount' => 35000, 'delivery_fee' => 2500,
                'date' => now()->format('d M Y, h:i A'),
            ]));
            \App\Http\Controllers\MailController::send_mail($data, 'email.order_shipped', $pdf);
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        // 7. Order Delivered (no PDF needed)
        $this->info("\n7. Order Delivered email...");
        try {
            $data = [
                'email' => $testEmail, 'username' => 'Habukhan',
                'title' => '✅ Order Delivered - MP_TEST_001 | Vendlike',
                'reference' => 'MP_TEST_001', 'grand_total' => 37500,
                'items' => [['name' => 'Nike Air Max 90', 'quantity' => 1], ['name' => 'Vendlike Branded T-Shirt', 'quantity' => 2]],
                'date' => now()->format('d M Y, h:i A'),
            ];
            \App\Http\Controllers\MailController::send_mail($data, 'email.order_delivered');
            $this->info("   ✅ Sent!");
        } catch (\Exception $e) { $this->error("   ❌ " . $e->getMessage()); }

        $this->info("\n=== ALL 7 TESTS COMPLETE ===");
        $this->info("Check inbox: $testEmail");
        return 0;
    }
}
