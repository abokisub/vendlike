<?php

namespace App\Services;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationService
{
    protected $firebaseService;

    public function __construct()
    {
        $this->firebaseService = new FirebaseService();
    }

    /**
     * Resilient Store and Push mechanism
     * Standardizes all notification logic and ensures it's non-blocking.
     */
    private function storeAndPush($user, array $data, $image = null)
    {
        try {
            // 1. Unified DB Record
            DB::table('notif')->insert([
                'username' => $user->username,
                'message' => $data['message'],
                'date' => Carbon::now("Africa/Lagos"),
                'habukhan' => 0, // Legacy field from schema
                'image_url' => $image
            ]);

            // 2. Firebase Push (if token exists)
            if ($user->app_token) {
                // Merge payload with metadata, ensuring channel_id and audio_type are at top level
                $payload = array_merge($data['payload'] ?? [], [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type' => $data['type'] ?? 'general',
                    'channel_id' => $data['channel_id'] ?? 'high_importance_channel',
                    'audio_type' => $data['audio_type'] ?? null
                ]);

                // Remove null values
                $payload = array_filter($payload, function ($value) {
                    return $value !== null;
                });

                // Debug logging
                Log::info("Sending notification to {$user->username}", [
                    'title' => $data['title'],
                    'type' => $data['type'] ?? 'general',
                    'channel_id' => $payload['channel_id'] ?? 'not_set',
                    'audio_type' => $payload['audio_type'] ?? 'not_set',
                    'has_token' => !empty($user->app_token)
                ]);

                $this->firebaseService->sendNotification(
                    $user->app_token,
                    $data['title'],
                    $data['message'],
                    $payload,
                    $image,
                    true  // DATA-ONLY: Let Flutter handle display for foreground notifications
                );
            }
        } catch (\Exception $e) {
            Log::error("Notification Delivery Failed: " . $e->getMessage(), [
                'user' => $user->username,
                'type' => $data['type'] ?? 'unspecified'
            ]);
        }
    }

    /**
     * Send Internal Wallet Credit Notification (Wallet to Wallet)
     */
    public function sendInternalCreditNotification($user, $amount, $senderName)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'wallet_credit',
            'title' => "💰 Credit Alert",
            'message' => "You received ₦{$formattedAmount} from {$senderName}.\nBalance updated successfully ✅",
            'payload' => [
                'action' => 'credit_internal',
                'amount' => $amount,
                'sender' => $senderName,
                'audio_type' => 'internal'
            ]
        ]);
    }

    /**
     * Send External Wallet Credit Notification (Bank/Virtual Account)
     */
    public function sendExternalCreditNotification($user, $amount, $reference)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'wallet_credit',
            'title' => "🏦 Credit Alert",
            'message' => "₦{$formattedAmount} has been credited to your wallet.\nRef: {$reference}",
            'payload' => [
                'action' => 'credit_external',
                'amount' => $amount,
                'reference' => $reference,
                'audio_type' => 'bank'
            ]
        ]);
    }

    /**
     * Send Wallet Debit Notification (Generic Debit)
     */
    public function sendDebitNotification($user, $amount, $serviceName)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'wallet_debit',
            'title' => "💸 Debit Alert",
            'message' => "₦{$formattedAmount} deducted for {$serviceName}.\nBalance updated.",
            'payload' => [
                'action' => 'debit',
                'amount' => $amount,
                'service' => $serviceName
            ]
        ]);
    }

    /**
     * Send Airtime Purchase Notification
     */
    public function sendAirtimeNotification($user, $amount, $network, $phone, $ref)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "📱 Airtime Purchase",
            'message' => "You purchased ₦{$formattedAmount} {$network} Airtime for {$phone} successfully. ✅",
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'airtime',
                'amount' => $amount,
                'reference' => $ref
            ]
        ]);
    }

    /**
     * Send Data Purchase Notification
     */
    public function sendDataNotification($user, $amount, $network, $plan, $phone, $ref)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "🌐 Data Purchase",
            'message' => "You purchased {$network} {$plan} for {$phone} successfully. ₦{$formattedAmount} deducted. ✅",
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'data',
                'amount' => $amount,
                'reference' => $ref
            ]
        ]);
    }

    /**
     * Send Electricity Bill Notification
     */
    public function sendBillNotification($user, $amount, $disco, $meter, $token, $ref)
    {
        $formattedAmount = number_format($amount, 2);
        $message = "You paid ₦{$formattedAmount} for {$disco} ({$meter}). ✅";
        if ($token) {
            $message .= "\nToken: {$token}";
        }

        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "⚡ Electricity Bill Paid",
            'message' => $message,
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'bill',
                'amount' => $amount,
                'reference' => $ref,
                'token' => $token
            ]
        ]);
    }

    /**
     * Send Cable TV Notification
     */
    public function sendCableNotification($user, $amount, $cable, $msg_plan, $iuc, $ref)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "📺 Cable TV Subscription",
            'message' => "You subscribed {$cable} {$msg_plan} for IUC: {$iuc} successfully. ₦{$formattedAmount} deducted. ✅",
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'cable',
                'amount' => $amount,
                'reference' => $ref
            ]
        ]);
    }

    /**
     * Send Exam PIN Notification
     */
    public function sendExamPinNotification($user, $amount, $examName, $quantity, $ref)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "📝 Exam PIN Generated",
            'message' => "Your {$examName} Exam PIN (Qty: {$quantity}) has been generated successfully. ₦{$formattedAmount} deducted. ✅",
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'exam_pin',
                'amount' => $amount,
                'reference' => $ref
            ]
        ]);
    }

    /**
     * Send Recharge Card Notification
     */
    public function sendRechargeCardNotification($user, $amount, $network, $quantity, $ref)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "🎫 Recharge Card Printed",
            'message' => "Your {$network} Recharge Card (Qty: {$quantity}) has been printed successfully. ₦{$formattedAmount} deducted. ✅",
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'recharge_card',
                'amount' => $amount,
                'reference' => $ref
            ]
        ]);
    }

    /**
     * Send Airtime-to-Cash Notification
     */
    public function sendAirtimeToCashNotification($user, $amount, $creditAmount, $network, $ref)
    {
        $formattedCredit = number_format($creditAmount, 2);
        $this->storeAndPush($user, [
            'type' => 'transaction',
            'title' => "💵 Airtime Converted",
            'message' => "Your {$network} airtime (₦{$amount}) has been converted. ₦{$formattedCredit} will be credited to your account. ✅",
            'channel_id' => 'high_importance_channel',
            'audio_type' => 'general',
            'payload' => [
                'action' => 'airtime_to_cash',
                'amount' => $creditAmount,
                'reference' => $ref
            ]
        ]);
    }

    /**
     * Send Welcome Notification (After Registration)
     */
    public function sendWelcomeNotification($user)
    {
        $this->storeAndPush($user, [
            'type' => 'welcome',
            'title' => "🎉 Welcome to VendLike!",
            'message' => "Hi {$user->name}, welcome aboard! Your account has been created successfully. Start enjoying seamless transactions with VendLike today!"
        ]);
    }

    /**
     * Send Login Notification
     */
    public function sendLoginNotification($user)
    {
        $this->storeAndPush($user, [
            'type' => 'login',
            'title' => "👋 Welcome Back!",
            'message' => "Hi {$user->name}, you've successfully logged in to your VendLike account."
        ]);
    }

    /**
     * Send Charity Donation Notification (To Donor)
     */
    public function sendCharityDonationNotification($user, $charityName, $amount)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'charity',
            'title' => "Donation Successful",
            'message' => "You successfully donated ₦{$formattedAmount} to {$charityName}. Thank you for your generosity!"
        ]);
    }

    /**
     * Send Charity Payout Notification (To Charity Owner)
     */
    public function sendCharityPayoutNotification($user, $charityName, $amount)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'charity_payout',
            'title' => "Payout Processed",
            'message' => "A payout of ₦{$formattedAmount} for {$charityName} has been processed and is now available in your balance."
        ]);
    }

    /**
     * Send KYC Status Notification
     */
    public function sendKycStatusNotification($user, $status, $reason = null)
    {
        if ($status === 'approved') {
            $title = "KYC Approved";
            $message = "Congratulations! Your KYC verification has been approved. You now have full access.";
        } else {
            $title = "KYC Update";
            $message = "Your KYC status is {$status}." . ($reason ? " Reason: {$reason}" : "");
        }

        $this->storeAndPush($user, [
            'type' => 'kyc',
            'title' => $title,
            'message' => $message,
            'payload' => ['status' => $status]
        ]);
    }

    /**
     * Send Security Notification (Password/PIN Change)
     */
    public function sendSecurityNotification($user, $action)
    {
        $this->storeAndPush($user, [
            'type' => 'security',
            'title' => "Security Alert",
            'message' => "Your {$action} was recently changed. If this wasn't you, please contact support immediately."
        ]);
    }

    /**
     * Send Referral Bonus Notification
     */
    public function sendReferralBonusNotification($user, $amount, $referredUser)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'referral_bonus',
            'title' => "🎁 Referral Bonus Earned!",
            'message' => "You earned ₦{$formattedAmount} referral bonus from {$referredUser}'s registration. Keep sharing!",
            'payload' => [
                'amount' => $amount,
                'referred_user' => $referredUser
            ]
        ]);
    }

    /**
     * Send Wallet Credit Notification (Dynamic Type)
     */
    public function sendWalletCreditNotification($user, $amount, $type, $reference)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'wallet_credit',
            'title' => "💰 Wallet Credited",
            'message' => "₦{$formattedAmount} has been credited to your wallet via {$type}.\nRef: {$reference}",
            'payload' => [
                'amount' => $amount,
                'reference' => $reference,
                'source' => $type,
                'audio_type' => stripos($type, 'Admin') !== false ? 'admin' : 'general'
            ]
        ]);
    }

    /**
     * Send Wallet Debit Notification (Dynamic Service)
     */
    public function sendWalletDebitNotification($user, $amount, $serviceName, $reference)
    {
        $formattedAmount = number_format($amount, 2);
        $this->storeAndPush($user, [
            'type' => 'wallet_debit',
            'title' => "💸 Wallet Debited",
            'message' => "₦{$formattedAmount} was deducted for {$serviceName}.\nRef: {$reference}",
            'payload' => [
                'amount' => $amount,
                'reference' => $reference,
                'reason' => $serviceName
            ]
        ]);
    }

    /**
     * Send Service Purchase Status Notification (Success/Failed)
     */
    public function sendServicePurchaseNotification($user, $serviceName, $amount, $status, $reference)
    {
        $formattedAmount = number_format($amount, 2);
        $emoji = $status === 'success' ? "✅" : "❌";
        $this->storeAndPush($user, [
            'type' => 'service_transaction',
            'title' => "🛒 " . ucfirst($serviceName) . " Transaction",
            'message' => "Your " . ucfirst($serviceName) . " of ₦{$formattedAmount} was " . strtoupper($status) . " {$emoji}\nRef: {$reference}",
            'payload' => [
                'status' => $status,
                'amount' => $amount,
                'reference' => $reference,
                'service' => $serviceName
            ]
        ]);
    }

    /**
     * ────────────────────────────────────────────────────────────
     * ADMIN ALERT METHODS
     * These send email + push alerts to all admin users.
     * ────────────────────────────────────────────────────────────
     */
    private function notifyAdmins(string $title, string $body, string $subject, array $attachments = []): void
    {
        try {
            $admins = DB::table('user')->where('type', 'admin')->whereNotNull('email')->get();

            foreach ($admins as $admin) {
                // Push notification to admin's device (if token exists)
                if ($admin->app_token) {
                    try {
                        $this->firebaseService->sendNotification(
                            $admin->app_token,
                            $title,
                            $body,
                            ['type' => 'admin_alert', 'audio_type' => 'bank'],
                            null,
                            true
                        );
                    } catch (\Exception $e) {
                        Log::warning("Admin push failed for {$admin->username}: " . $e->getMessage());
                    }
                }

                // Email to admin
                try {
                    \App\Http\Controllers\MailController::send_mail([
                        'email' => $admin->email,
                        'username' => $admin->username ?? 'Admin',
                        'title' => $subject,
                        'body' => $body,
                        'app_name' => config('app.name'),
                    ], 'email.admin_alert', $attachments);
                } catch (\Exception $e) {
                    Log::warning("Admin email failed for {$admin->username}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Admin notification error: ' . $e->getMessage());
        }
    }

    /**
     * Alert admins when a new marketplace order is placed.
     */
    public function sendAdminMarketplaceOrderAlert($user, $order, array $items): void
    {
        $userName = $user->name ?? $user->username;
        $itemCount = count($items);
        $itemList = collect($items)->pluck('product_name')->take(3)->join(', ');
        $grandTotal = number_format($order->grand_total ?? 0, 2);
        $ref = $order->reference ?? 'N/A';

        $body = "New Marketplace Order #{$ref}\nCustomer: {$userName}\nItems ({$itemCount}): {$itemList}\nTotal: ₦{$grandTotal}\n\nLogin to admin panel to process this order.";
        $subject = "🛒 New Order ₦{$grandTotal} | {$ref} | " . config('app.name');

        $this->notifyAdmins("🛒 New Order - ₦{$grandTotal}", $body, $subject, []);
    }

    /**
     * Alert admins when a user submits an airtime-to-cash request.
     */
    public function sendAdminAirtimeToCashAlert($user, $amount, $network, $ref): void
    {
        $userName = $user->name ?? $user->username;
        $formatted = number_format($amount, 2);

        $body = "New Airtime-to-Cash Submission\nCustomer: {$userName} ({$user->username})\nNetwork: {$network}\nAmount: ₦{$formatted}\nRef: {$ref}\n\nLogin to admin panel to verify and credit the user.";
        $subject = "💵 A2C Request ₦{$formatted} | {$network} | " . config('app.name');

        $this->notifyAdmins("💵 New A2C Request - ₦{$formatted}", $body, $subject, []);
    }

    /**
     * Alert admins when a user submits a gift card for sale/redemption.
     */
    public function sendAdminGiftCardAlert($user, $cardType, $amount, $ref, array $attachments = []): void
    {
        $userName = $user->name ?? $user->username;
        $formatted = number_format($amount, 2);

        $body = "New Gift Card Sale Submission\nCustomer: {$userName} ({$user->username})\nCard Type: {$cardType}\nDeclared Value: ₦{$formatted}\nRef: {$ref}\n\nLogin to admin panel to review and approve.";
        $subject = "🎁 New Gift Card Sale ₦{$formatted} | {$cardType} | " . config('app.name');

        $this->notifyAdmins("🎁 Gift Card Sale - ₦{$formatted}", $body, $subject, $attachments);
    }
}