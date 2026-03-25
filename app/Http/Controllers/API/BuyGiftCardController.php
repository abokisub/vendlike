<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\GiftCardHelpers;
use App\Models\GiftCardPurchase;
use App\Services\ReloadlyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BuyGiftCardController extends Controller
{
    use GiftCardHelpers;

    private ReloadlyService $reloadly;

    public function __construct()
    {
        $this->reloadly = new ReloadlyService();
    }

    /**
     * Helper: get the admin's markup percentage on Reloadly cost
     */
    private function getMarkupPercentage(): float
    {
        $settings = DB::table('settings')->first();
        return (float) ($settings->buy_giftcard_markup ?? 5.00);
    }

    /**
     * Helper: get the admin's USD to NGN rate
     */
    private function getDollarRate(): float
    {
        $settings = DB::table('settings')->first();
        return (float) ($settings->buy_giftcard_dollar_rate ?? 1500.00);
    }

    /**
     * Helper: calculate selling price in NGN from Reloadly sender amount (USD) + dollar rate + markup
     * Flow: USD cost × dollar rate = NGN cost → NGN cost × (1 + markup%) = user pays
     * @param float $senderAmount - What Reloadly charges us (in USD)
     * @param float $markupPercent - Admin's markup percentage
     * @param float $dollarRate - Admin's USD to NGN rate
     * @return float - What we charge the user in NGN
     */
    private function applyMarkup(float $senderAmount, float $markupPercent, float $dollarRate = 0): float
    {
        if ($dollarRate > 0) {
            $ngnCost = $senderAmount * $dollarRate;
        } else {
            $ngnCost = $senderAmount;
        }
        return round($ngnCost * (1 + $markupPercent / 100), 2);
    }

    /**
     * Helper: check if buy gift card feature is enabled
     */
    private function isEnabled(): bool
    {
        $settings = DB::table('settings')->first();
        // Check both columns for backward compatibility
        if (($settings->buy_giftcard_lock ?? 0) == 1) return false;
        return (bool) ($settings->buy_giftcard_status ?? 1);
    }

    // ========================================
    // USER ENDPOINTS (Mobile App)
    // ========================================

    /**
     * Get supported countries for buying gift cards
     */
    public function getCountries(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $result = $this->reloadly->getCountries();
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        return response()->json(['status' => 'success', 'data' => $result['data']]);
    }

    /**
     * Get gift card categories
     */
    public function getCategories(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $result = $this->reloadly->getCategories();
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        return response()->json(['status' => 'success', 'data' => $result['data']]);
    }

    /**
     * Get gift card products (with optional filters)
     * Adds our selling rate so mobile can calculate NGN price
     */
    public function getProducts(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $filters = $request->only(['size', 'page', 'productName', 'countryCode', 'productCategoryId', 'includeRange', 'includeFixed']);
        $result = $this->reloadly->getProducts($filters);

        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        $markupPercent = $this->getMarkupPercentage();
        $dollarRate = $this->getDollarRate();

        // Reloadly paginated response has products in 'content' key
        $data = $result['data'];
        $products = is_array($data) && isset($data['content']) ? $data['content'] : $data;

        return response()->json([
            'status' => 'success',
            'data' => $products,
            'markup_percentage' => $markupPercent,
            'dollar_rate' => $dollarRate,
        ]);
    }

    /**
     * Get products by country ISO code
     */
    public function getProductsByCountry(Request $request, $countryCode)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $result = $this->reloadly->getProductsByCountry($countryCode);
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        $markupPercent = $this->getMarkupPercentage();
        $dollarRate = $this->getDollarRate();

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
            'markup_percentage' => $markupPercent,
            'dollar_rate' => $dollarRate,
        ]);
    }

    /**
     * Get single product details
     */
    public function getProduct(Request $request, $productId)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $result = $this->reloadly->getProduct((int) $productId);
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        $markupPercent = $this->getMarkupPercentage();
        $dollarRate = $this->getDollarRate();

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
            'markup_percentage' => $markupPercent,
            'dollar_rate' => $dollarRate,
        ]);
    }

    /**
     * Get redeem instructions for a product
     */
    public function getRedeemInstructions(Request $request, $productId)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $result = $this->reloadly->getRedeemInstructions((int) $productId);
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        return response()->json(['status' => 'success', 'data' => $result['data']]);
    }

    /**
     * Purchase a gift card
     * Deducts from user's main wallet, calls Reloadly, returns card code
     */
    public function purchaseGiftCard(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        if (!$this->isEnabled()) {
            return response()->json(['status' => 'fail', 'message' => 'Gift card purchase is currently unavailable'], 503);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'unit_price' => 'required|numeric|min:1',
            'quantity' => 'required|integer|min:1|max:10',
            'recipient_email' => 'nullable|email',
            'pin' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $user = DB::table('user')->where('id', $user_id)->first();
        if (!$user) {
            return response()->json(['status' => 'fail', 'message' => 'User not found'], 404);
        }

        // Verify transaction PIN
        if ($user->pin != $request->pin) {
            return response()->json(['status' => 'fail', 'message' => 'Invalid transaction PIN'], 403);
        }

        // Get product details from Reloadly to validate price
        $productResult = $this->reloadly->getProduct((int) $request->product_id);
        if ($productResult['error']) {
            return response()->json(['status' => 'fail', 'message' => 'Gift card product not available'], 400);
        }

        $product = $productResult['data'];
        $unitPrice = (float) $request->unit_price; // recipient currency amount (e.g. $25 USD)
        $quantity = (int) $request->quantity;

        // Validate the price against product denomination
        $senderAmount = 0; // What Reloadly charges us per card (in sender currency, typically NGN)

        if ($product['denominationType'] === 'FIXED') {
            $validPrices = $product['fixedRecipientDenominations'] ?? [];
            // Loose comparison — Reloadly returns [60, 80, 120], user might send 60 or 60.0
            $priceValid = false;
            foreach ($validPrices as $vp) {
                if (abs((float)$vp - $unitPrice) < 0.01) {
                    $priceValid = true;
                    $unitPrice = (float) $vp; // Normalize to exact Reloadly value
                    break;
                }
            }
            if (!$priceValid) {
                return response()->json(['status' => 'fail', 'message' => 'Invalid price for this gift card'], 400);
            }
            // Get the sender cost from the denomination map
            // Reloadly returns keys like "60.0", "80.0" etc — try multiple key formats
            $denomMap = $product['fixedRecipientToSenderDenominationsMap'] ?? [];
            $senderAmount = 0;
            $lookupKeys = [
                (string) $unitPrice,           // "60"
                number_format($unitPrice, 1, '.', ''),  // "60.0"
                number_format($unitPrice, 2, '.', ''),  // "60.00"
            ];
            foreach ($lookupKeys as $key) {
                if (isset($denomMap[$key])) {
                    $senderAmount = (float) $denomMap[$key];
                    break;
                }
            }
            if ($senderAmount <= 0) {
                // Numeric comparison fallback (handles keys like "25.000", "25.0000" etc.)
                foreach ($denomMap as $key => $val) {
                    if (abs((float)$key - $unitPrice) < 0.01) {
                        $senderAmount = (float) $val;
                        break;
                    }
                }
            }
            if ($senderAmount <= 0) {
                // Fallback: use fixedSenderDenominations at same index
                $recipientDenoms = $product['fixedRecipientDenominations'] ?? [];
                $senderDenoms = $product['fixedSenderDenominations'] ?? [];
                $idx = array_search($unitPrice, $recipientDenoms);
                $senderAmount = ($idx !== false && isset($senderDenoms[$idx])) ? (float) $senderDenoms[$idx] : 0;
            }
            if ($senderAmount <= 0) {
                return response()->json(['status' => 'fail', 'message' => 'Unable to determine cost for this denomination'], 400);
            }
        } else {
            // RANGE type
            $min = $product['minRecipientDenomination'] ?? 0;
            $max = $product['maxRecipientDenomination'] ?? 0;
            if ($unitPrice < $min || $unitPrice > $max) {
                return response()->json(['status' => 'fail', 'message' => "Amount must be between $min and $max"], 400);
            }
            // For RANGE, calculate sender amount using the FX rate from product
            $fxRate = (float) ($product['recipientCurrencyToSenderCurrencyExchangeRate'] ?? 0);
            if ($fxRate <= 0) {
                return response()->json(['status' => 'fail', 'message' => 'Exchange rate unavailable for this product'], 400);
            }
            $senderAmount = round($unitPrice * $fxRate, 2);
        }

        // Calculate costs: USD cost × dollar rate → NGN cost + markup
        $markupPercent = $this->getMarkupPercentage();
        $dollarRate = $this->getDollarRate();
        $totalSenderCost = $senderAmount * $quantity; // Total Reloadly cost in USD
        $nairaAmount = $this->applyMarkup($totalSenderCost, $markupPercent, $dollarRate); // What user pays in NGN

        // Check user balance
        if ($user->bal < $nairaAmount) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Insufficient balance. You need ₦' . number_format($nairaAmount, 2),
            ], 400);
        }

        // Generate reference
        $reference = $this->purchase_ref('BG_');

        try {
            DB::beginTransaction();

            // Deduct from user's main wallet
            $oldBal = $user->bal;
            $newBal = $oldBal - $nairaAmount;
            DB::table('user')->where('id', $user_id)->update(['bal' => $newBal]);

            // Create local purchase record
            $purchase = GiftCardPurchase::create([
                'user_id' => $user_id,
                'reference' => $reference,
                'product_id' => $request->product_id,
                'product_name' => $product['productName'] ?? '',
                'brand_name' => $product['brand']['brandName'] ?? null,
                'country_code' => $product['country']['isoName'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
                'recipient_currency' => $product['recipientCurrencyCode'] ?? 'USD',
                'sender_amount' => $totalSenderCost,
                'naira_amount' => $nairaAmount,
                'exchange_rate' => $markupPercent,
                'reloadly_rate' => $product['recipientCurrencyToSenderCurrencyExchangeRate'] ?? null,
                'recipient_email' => $request->recipient_email,
                'logo_url' => $product['logoUrls'][0] ?? null,
                'status' => 'pending',
            ]);

            // Insert into message table for transaction history
            DB::table('message')->insert([
                'username' => $user->username,
                'message' => 'Buy Gift Card - ' . ($product['productName'] ?? 'Gift Card'),
                'amount' => $nairaAmount,
                'oldbal' => $oldBal,
                'newbal' => $newBal,
                'habukhan_date' => Carbon::now(),
                'transid' => $reference,
                'plan_status' => 0,
                'role' => 'debit',
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Buy gift card DB error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'fail', 'message' => 'Transaction failed'], 500);
        }

        // Call Reloadly to purchase the gift card
        $orderData = [
            'productId' => (int) $request->product_id,
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'customIdentifier' => $reference,
            'senderName' => $user->name ?? $user->username,
        ];

        if ($request->recipient_email) {
            // NOTE: Do NOT pass recipientEmail to Reloadly — it triggers Reloadly's own branded email.
            // We send our own branded email instead via sendPurchaseEmail().
            // $orderData['recipientEmail'] = $request->recipient_email;
        }

        $orderResult = $this->reloadly->orderGiftCard($orderData);

        if ($orderResult['error']) {
            // Reloadly failed — refund user
            Log::error('Reloadly order failed, refunding', [
                'reference' => $reference,
                'error' => $orderResult['message'],
            ]);

            DB::table('user')->where('id', $user_id)->increment('bal', $nairaAmount);
            $purchase->update([
                'status' => 'failed',
                'error_message' => $orderResult['message'],
            ]);

            // Update message table
            DB::table('message')->where('transid', $reference)->update([
                'message' => 'Gift Card purchase failed - Refunded',
                'plan_status' => 2,
                'role' => 'refund',
                'newbal' => $oldBal,
            ]);

            return response()->json([
                'status' => 'fail',
                'message' => 'Gift card purchase failed. Your wallet has been refunded.',
            ], 400);
        }

        // Success — update purchase record with Reloadly data
        $txn = $orderResult['data'];
        $reloadlyCost = $txn['amount'] ?? $totalSenderCost;
        $reloadlyFee = $txn['fee'] ?? 0;
        $reloadlyDiscount = $txn['discount'] ?? 0;
        $profit = $nairaAmount - $reloadlyCost; // Our markup is the profit

        $purchase->update([
            'reloadly_transaction_id' => $txn['transactionId'] ?? null,
            'sender_amount' => $reloadlyCost,
            'reloadly_fee' => $reloadlyFee,
            'reloadly_discount' => $reloadlyDiscount,
            'profit' => max(0, $profit),
            'status' => strtolower($txn['status'] ?? 'successful') === 'successful' ? 'successful' : 'processing',
            'reloadly_status' => $txn['status'] ?? null,
        ]);

        // Update message table to success
        DB::table('message')->where('transid', $reference)->update([
            'message' => 'You have successfully purchased Gift Card',
            'plan_status' => 1,
            'habukhan_date' => Carbon::now(),
        ]);

        // Try to get redeem code
        $redeemData = null;
        $allCards = [];
        if (!empty($txn['transactionId'])) {
            $redeemResult = $this->reloadly->getRedeemCode($txn['transactionId']);
            if (!$redeemResult['error']) {
                $rawData = $redeemResult['data'];
                // Reloadly returns an array of cards for multi-quantity orders
                if (is_array($rawData) && isset($rawData[0])) {
                    $allCards = $rawData;
                    $redeemData = $rawData[0]; // First card for backward compat
                } else {
                    $redeemData = $rawData;
                    $allCards = [$rawData];
                }
                // Save first card details to purchase record
                $purchase->update([
                    'card_number' => $redeemData['cardNumber'] ?? null,
                    'pin_code' => $redeemData['pinCode'] ?? null,
                    'redemption_url' => $redeemData['redemptionUrl'] ?? null,
                ]);
            }
        }

        // Get redeem instructions
        $instructions = $this->reloadly->getRedeemInstructions((int) $request->product_id);
        if (!$instructions['error'] && !empty($instructions['data'])) {
            $purchase->update([
                'redeem_instructions_concise' => $instructions['data']['concise'] ?? null,
                'redeem_instructions_verbose' => $instructions['data']['verbose'] ?? null,
            ]);
        }

        // Send email to user's registered email with full card details
        $this->sendPurchaseEmail($user, $purchase, $redeemData, $instructions['data'] ?? null, $allCards);

        // Send push notification
        try {
            \App\Helpers\NotificationHelper::sendTransactionNotification(
                $user, 'debit', $nairaAmount,
                'Gift Card Purchase - ' . ($purchase->product_name ?? 'Gift Card'),
                $reference
            );
        } catch (\Exception $e) {
            Log::error('Gift card push notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Gift card purchased successfully',
            'data' => [
                'reference' => $reference,
                'product_name' => $purchase->product_name,
                'brand_name' => $purchase->brand_name,
                'quantity' => $purchase->quantity,
                'unit_price' => $purchase->unit_price,
                'total_price' => $purchase->total_price,
                'recipient_currency' => $purchase->recipient_currency,
                'naira_amount' => $purchase->naira_amount,
                'card_number' => $redeemData['cardNumber'] ?? null,
                'pin_code' => $redeemData['pinCode'] ?? null,
                'redemption_url' => $redeemData['redemptionUrl'] ?? null,
                'redeem_instructions' => $instructions['data']['concise'] ?? null,
                'logo_url' => $purchase->logo_url,
                'status' => $purchase->status,
                'transaction_id' => $txn['transactionId'] ?? null,
                'cards' => array_map(function ($card) {
                    return [
                        'card_number' => $card['cardNumber'] ?? null,
                        'pin_code' => $card['pinCode'] ?? null,
                        'redemption_url' => $card['redemptionUrl'] ?? null,
                    ];
                }, $allCards),
            ],
        ]);
    }

    /**
     * Get user's gift card purchase history
     */
    public function getPurchaseHistory(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $purchases = GiftCardPurchase::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->makeVisible(['card_number', 'pin_code'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'product_name' => $p->product_name,
                    'brand_name' => $p->brand_name,
                    'quantity' => $p->quantity,
                    'unit_price' => $p->unit_price,
                    'total_price' => $p->total_price,
                    'recipient_currency' => $p->recipient_currency,
                    'naira_amount' => $p->naira_amount,
                    'card_number' => $p->card_number,
                    'pin_code' => $p->pin_code,
                    'redemption_url' => $p->redemption_url,
                    'redeem_instructions' => $p->redeem_instructions_concise,
                    'logo_url' => $p->logo_url,
                    'status' => $p->status,
                    'created_at' => $p->created_at,
                ];
            });

        return response()->json(['status' => 'success', 'data' => $purchases]);
    }

    /**
     * Get single purchase details (with card code)
     */
    public function getPurchaseDetail(Request $request, $reference)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $purchase = GiftCardPurchase::where('user_id', $user_id)
            ->where('reference', $reference)
            ->first();

        if (!$purchase) {
            return response()->json(['status' => 'fail', 'message' => 'Purchase not found'], 404);
        }

        $purchase->makeVisible(['card_number', 'pin_code']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $purchase->id,
                'reference' => $purchase->reference,
                'product_name' => $purchase->product_name,
                'brand_name' => $purchase->brand_name,
                'country_code' => $purchase->country_code,
                'quantity' => $purchase->quantity,
                'unit_price' => $purchase->unit_price,
                'total_price' => $purchase->total_price,
                'recipient_currency' => $purchase->recipient_currency,
                'naira_amount' => $purchase->naira_amount,
                'exchange_rate' => $purchase->exchange_rate,
                'card_number' => $purchase->card_number,
                'pin_code' => $purchase->pin_code,
                'redemption_url' => $purchase->redemption_url,
                'redeem_instructions_concise' => $purchase->redeem_instructions_concise,
                'redeem_instructions_verbose' => $purchase->redeem_instructions_verbose,
                'recipient_email' => $purchase->recipient_email,
                'logo_url' => $purchase->logo_url,
                'status' => $purchase->status,
                'created_at' => $purchase->created_at,
            ],
        ]);
    }

    // ========================================
    // ADMIN ENDPOINTS
    // ========================================

    /**
     * Admin: Get buy gift card settings (rate, status)
     */
    public function getSettings(Request $request)
    {
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->route('id') ?? $request->token;
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where('type', 'ADMIN');
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $settings = DB::table('settings')->first();
        $balance = $this->reloadly->getBalance();

        return response()->json([
            'status' => 'success',
            'data' => [
                'buy_giftcard_markup' => $settings->buy_giftcard_markup ?? 5.00,
                'buy_giftcard_dollar_rate' => $settings->buy_giftcard_dollar_rate ?? 1500.00,
                'buy_giftcard_status' => $settings->buy_giftcard_status ?? 1,
                'buy_giftcard_lock' => $settings->buy_giftcard_lock ?? 0,
                'sell_giftcard_lock' => $settings->sell_giftcard_lock ?? 0,
                'buy_giftcard_provider' => $settings->buy_giftcard_provider ?? 'reloadly',
                'reloadly_balance' => $balance['error'] ? null : $balance['data'],
                'environment' => config('app.reloadly_environment', 'sandbox'),
            ],
        ]);
    }

    /**
     * Admin: Get all Reloadly gift card products (for admin catalog view)
     */
    public function adminGetProducts(Request $request)
    {
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->route('id') ?? $request->token;
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where('type', 'ADMIN');
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $filters = $request->only(['size', 'page', 'productName', 'countryCode', 'includeRange', 'includeFixed']);
        if (empty($filters['size'])) $filters['size'] = 200;
        if (!isset($filters['page'])) $filters['page'] = 1;

        $result = $this->reloadly->getProducts($filters);
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        $data = $result['data'];
        $products = is_array($data) && isset($data['content']) ? $data['content'] : $data;
        $totalElements = $data['totalElements'] ?? count($products);
        $totalPages = $data['totalPages'] ?? 1;
        $currentPage = $data['number'] ?? 0;

        return response()->json([
            'status' => 'success',
            'data' => $products,
            'markup_percentage' => $this->getMarkupPercentage(),
            'dollar_rate' => $this->getDollarRate(),
            'pagination' => [
                'total' => $totalElements,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'size' => $filters['size'],
            ],
        ]);
    }

    /**
     * Admin: Update buy gift card settings
     */
    public function updateSettings(Request $request)
    {
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->route('id') ?? $request->token;
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where('type', 'ADMIN');
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'buy_giftcard_markup' => 'nullable|numeric|min:0|max:100',
            'buy_giftcard_dollar_rate' => 'nullable|numeric|min:1',
            'buy_giftcard_status' => 'nullable|in:0,1',
            'buy_giftcard_lock' => 'nullable|in:0,1',
            'sell_giftcard_lock' => 'nullable|in:0,1',
            'buy_giftcard_provider' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $update = [];
        if ($request->has('buy_giftcard_markup')) {
            $update['buy_giftcard_markup'] = $request->buy_giftcard_markup;
        }
        if ($request->has('buy_giftcard_dollar_rate')) {
            $update['buy_giftcard_dollar_rate'] = $request->buy_giftcard_dollar_rate;
        }
        if ($request->has('buy_giftcard_status')) {
            $update['buy_giftcard_status'] = $request->buy_giftcard_status;
        }
        if ($request->has('buy_giftcard_lock')) {
            $update['buy_giftcard_lock'] = $request->buy_giftcard_lock;
        }
        if ($request->has('sell_giftcard_lock')) {
            $update['sell_giftcard_lock'] = $request->sell_giftcard_lock;
        }
        if ($request->has('buy_giftcard_provider')) {
            $update['buy_giftcard_provider'] = $request->buy_giftcard_provider;
        }

        if (!empty($update)) {
            DB::table('settings')->update($update);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * Admin: Get Reloadly account balance
     */
    public function getReloadlyBalance(Request $request)
    {
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->route('id') ?? $request->token;
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where('type', 'ADMIN');
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $result = $this->reloadly->getBalance();
        if ($result['error']) {
            return response()->json(['status' => 'fail', 'message' => $result['message']], 500);
        }

        return response()->json(['status' => 'success', 'data' => $result['data']]);
    }

    /**
     * Send purchase confirmation email to user's registered email
     */
    private function sendPurchaseEmail($user, $purchase, $redeemData, $instructions, $allCards = [])
    {
        if (empty($user->email)) return;

        try {
            $appName = config('app.name', 'Vendlike');
            $redeemInstructions = $instructions['concise'] ?? ($instructions['verbose'] ?? null);

            // Use allCards if available, otherwise fallback to single redeemData
            if (empty($allCards) && $redeemData) {
                $allCards = [$redeemData];
            }

            $html = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#f9fafb;border-radius:12px;">
                <div style="text-align:center;padding:20px 0;">
                    <h2 style="color:#1a1a2e;margin:0;">🎁 Gift Card Purchase Successful</h2>
                    <p style="color:#6b7280;margin:8px 0 0;">Your gift card is ready!</p>
                </div>
                <div style="background:#fff;border-radius:8px;padding:24px;margin:16px 0;border:1px solid #e5e7eb;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Product</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">' . htmlspecialchars($purchase->product_name ?? 'Gift Card') . '</td></tr>
                        <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Brand</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">' . htmlspecialchars($purchase->brand_name ?? '-') . '</td></tr>
                        <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Quantity</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">' . ($purchase->quantity ?? 1) . '</td></tr>
                        <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Card Value</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:14px;">' . ($purchase->recipient_currency ?? 'USD') . ' ' . number_format($purchase->unit_price ?? 0, 2) . '</td></tr>
                        <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Amount Paid</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:16px;color:#16a34a;">₦' . number_format($purchase->naira_amount ?? 0, 2) . '</td></tr>
                        <tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Reference</td><td style="padding:8px 0;text-align:right;font-size:13px;font-family:monospace;">' . ($purchase->reference ?? '-') . '</td></tr>
                    </table>
                </div>';

            // Card details section — show ALL cards for multi-quantity
            if (!empty($allCards)) {
                $cardCount = count($allCards);
                $html .= '<div style="background:#f0fdf4;border-radius:8px;padding:20px;margin:16px 0;border:1px solid #bbf7d0;">
                    <h3 style="color:#15803d;margin:0 0 12px;font-size:16px;">🔑 Your Gift Card Details' . ($cardCount > 1 ? ' (' . $cardCount . ' cards)' : '') . '</h3>';

                foreach ($allCards as $idx => $card) {
                    $cardNumber = $card['cardNumber'] ?? null;
                    $pinCode = $card['pinCode'] ?? null;
                    $redemptionUrl = $card['redemptionUrl'] ?? null;

                    if ($cardNumber || $pinCode || $redemptionUrl) {
                        if ($cardCount > 1) {
                            $html .= '<div style="border-top:1px solid #bbf7d0;padding-top:12px;margin-top:12px;">
                                <p style="margin:0 0 6px;font-size:13px;color:#166534;font-weight:600;">Card ' . ($idx + 1) . '</p>';
                        }
                        if ($cardNumber) {
                            $html .= '<p style="margin:6px 0;font-size:14px;"><strong>Card Number:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">' . htmlspecialchars($cardNumber) . '</span></p>';
                        }
                        if ($pinCode) {
                            $html .= '<p style="margin:6px 0;font-size:14px;"><strong>PIN Code:</strong> <span style="font-family:monospace;background:#fff;padding:4px 8px;border-radius:4px;">' . htmlspecialchars($pinCode) . '</span></p>';
                        }
                        if ($redemptionUrl) {
                            $html .= '<p style="margin:10px 0 0;"><a href="' . htmlspecialchars($redemptionUrl) . '" style="display:inline-block;background:#16a34a;color:#fff;padding:8px 20px;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">Redeem Card' . ($cardCount > 1 ? ' ' . ($idx + 1) : '') . '</a></p>';
                        }
                        if ($cardCount > 1) {
                            $html .= '</div>';
                        }
                    }
                }
                $html .= '</div>';
            }

            // Redeem instructions
            if ($redeemInstructions) {
                $html .= '<div style="background:#fff;border-radius:8px;padding:16px;margin:16px 0;border:1px solid #e5e7eb;">
                    <h4 style="color:#374151;margin:0 0 8px;font-size:14px;">How to Redeem</h4>
                    <p style="color:#6b7280;font-size:13px;margin:0;line-height:1.5;">' . htmlspecialchars($redeemInstructions) . '</p>
                </div>';
            }

            $html .= '<div style="text-align:center;padding:16px 0;color:#9ca3af;font-size:12px;">
                    <p>Thank you for using ' . htmlspecialchars($appName) . '</p>
                    <p style="font-size:11px;">A PDF invoice is attached to this email for your records.</p>
                </div>
            </div>';

            // Generate PDF invoice
            $pdfAttachment = \App\Services\InvoiceService::generatePdf('GIFT_CARD', [
                'invoice_type' => 'GIFT CARD INVOICE',
                'reference' => $purchase->reference,
                'status' => 'SUCCESSFUL',
                'customer_name' => $user->name ?? $user->username,
                'customer_email' => $user->email,
                'username' => $user->username,
                'date' => now()->format('d M Y, h:i A'),
                'product_name' => $purchase->product_name,
                'brand_name' => $purchase->brand_name,
                'quantity' => $purchase->quantity,
                'unit_price' => $purchase->unit_price,
                'currency' => $purchase->recipient_currency ?? 'USD',
                'naira_amount' => $purchase->naira_amount,
                'cards' => $allCards,
                'redeem_instructions' => $redeemInstructions,
            ]);

            Mail::html($html, function ($message) use ($user, $purchase, $appName, $pdfAttachment) {
                $message->to($user->email, $user->name ?? $user->username)
                    ->subject('🎁 Gift Card Purchase - ' . ($purchase->product_name ?? 'Gift Card') . ' | ' . $appName);
                if ($pdfAttachment) {
                    $message->attachData($pdfAttachment['data'], $pdfAttachment['name'], ['mime' => $pdfAttachment['mime']]);
                }
            });

            Log::info('Gift card purchase email sent', ['user' => $user->username, 'email' => $user->email, 'reference' => $purchase->reference, 'cards_count' => count($allCards)]);
        } catch (\Exception $e) {
            // Don't fail the purchase if email fails
            Log::error('Gift card purchase email failed', ['error' => $e->getMessage(), 'user' => $user->username ?? '']);
        }
    }
}
