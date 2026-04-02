<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SudoService;
use App\Services\Banking\Providers\XixapayProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DollarCardController extends Controller
{
    /** @var SudoService */
    protected $sudo;

    /** @var XixapayProvider */
    protected $xixapay;

    public function __construct()
    {
        $this->sudo = new SudoService();
        $this->xixapay = new XixapayProvider();
    }

    /**
     * @param string|null $providerName
     * @return SudoService|XixapayProvider
     */
    private function getProvider($providerName = null)
    {
        if (!$providerName) {
            $settings = $this->getCardSettings();
            $providerName = $settings->dollar_card_provider ?? 'sudo';
        }
        return ($providerName === 'xixapay') ? $this->xixapay : $this->sudo;
    }

    private function getCardSettings()
    {
        return DB::table('card_settings')->where('id', 1)->first();
    }

    private function getActiveRate($settings, $type = 'buy')
    {
        $providerName = $settings->dollar_card_provider ?? 'sudo';

        if ($providerName === 'xixapay') {
            if ($type === 'buy') {
                return (float) ($settings->xixapay_manual_buy_rate ?? 0);
            }
            return (float) ($settings->xixapay_manual_sell_rate ?? 0);
        }

        // Sudo rates
        $rateSource = $settings->sudo_rate_source ?? 'manual';
        if ($rateSource === 'auto') {
            if ($type === 'buy' && !empty($settings->sudo_auto_buy_rate)) {
                return (float) $settings->sudo_auto_buy_rate;
            }
            if ($type === 'sell' && !empty($settings->sudo_auto_sell_rate)) {
                return (float) $settings->sudo_auto_sell_rate;
            }
        }
        if ($type === 'sell' && !empty($settings->sudo_manual_sell_rate)) {
            return (float) $settings->sudo_manual_sell_rate;
        }

        return (float) ($settings->sudo_dollar_rate ?? 1500);
    }

    // ─── USER: CREATE CARD ───────────────────────────────────────

    public function createCard(Request $request)
    {
        set_time_limit(120);
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        if (!$user)
            return response()->json(['status' => 'error', 'message' => 'User not found'], 401);

        // Check lock (Agnostic)
        $settings = $this->getCardSettings();
        $isLocked = ($settings->card_lock ?? 0) == 1 || ($settings->sudo_card_lock ?? 0) == 1;
        if ($isLocked) {
            return response()->json(['status' => 'error', 'message' => 'Dollar Card service is currently unavailable'], 403);
        }

        $providerName = $settings->dollar_card_provider ?? 'sudo';
        $provider = $this->getProvider($providerName);

        // Check existing active card for this provider
        $existingCard = DB::table('virtual_cards')
            ->where('user_id', $user->id)
            ->where('provider', $providerName)
            ->whereNotIn('status', ['terminated', 'canceled'])
            ->first();

        if ($existingCard) {
            return response()->json(['status' => 'error', 'message' => 'You already have an active dollar card with this provider'], 400);
        }

        $validator = Validator::make($request->all(), [
            'pin' => 'required|numeric|digits:4',
            'amount' => 'required|numeric|min:3',
        ]);
        if ($validator->fails())
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        if ($request->pin != $user->pin) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Transaction PIN'], 403);
        }

        $fundingAmountUsd = (float) $request->amount;
        $dollarRate = $this->getActiveRate($settings, 'buy');
        $fundingFeePercent = ($providerName === 'xixapay') ? ($settings->xixapay_funding_fee_percent ?? 1.5) : ($settings->sudo_funding_fee_percent ?? 1.5);

        // Total NGN = initial USD load + service fee
        $baseCostNgn = $fundingAmountUsd * $dollarRate;
        $feeNgn = $baseCostNgn * ($fundingFeePercent / 100);
        $totalCostNgn = $baseCostNgn + $feeNgn;

        if ($user->bal < $totalCostNgn) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient funds. Required: ₦' . number_format($totalCostNgn, 2) . " (funds card with \${$fundingAmountUsd})"], 400);
        }

        try {
            DB::beginTransaction();

            $transId = 'CARD_CREATE_' . strtoupper(bin2hex(random_bytes(4)));

            // 1. Deduct customer balance
            DB::table('user')->where('id', $user->id)->decrement('bal', $totalCostNgn);

            // 2. Log transaction
            DB::table('transaction')->insert([
                'username' => $user->username,
                'transid' => $transId,
                'type' => 'Dollar Card Creation',
                'amount' => $totalCostNgn,
                'oldbal' => $user->bal,
                'newbal' => $user->bal - $totalCostNgn,
                'status' => 'processing',
                'date' => now(),
            ]);

            // 3. Provider Specific Creation
            if ($providerName === 'sudo') {
                if (empty($user->sudo_customer_id)) {
                    throw new \Exception('Sudo Customer ID not found. Please contact support.');
                }
                $result = $provider->createVirtualCard($user->sudo_customer_id, 'USD', $fundingAmountUsd);
            } else {
                if (empty($user->customer_id)) {
                    throw new \Exception('Xixapay Customer ID not found. Please complete card registration.');
                }
                $result = $provider->createVirtualCard($user->customer_id, 'USD', $fundingAmountUsd);
            }

            if (isset($result['status']) && $result['status'] === 'success') {
                $cardData = $result['data'];
                DB::table('virtual_cards')->insert([
                    'user_id' => $user->id,
                    'provider' => $providerName,
                    'card_id' => $cardData['card_id'] ?? $cardData['id'],
                    'sudo_card_id' => ($providerName === 'sudo') ? ($cardData['card_id'] ?? $cardData['id']) : null,
                    'sudo_customer_id' => ($providerName === 'sudo') ? $user->sudo_customer_id : $user->customer_id,
                    'masked_pan' => $cardData['masked_pan'] ?? $cardData['pan'] ?? '•••• •••• •••• ' . ($cardData['last4'] ?? '••••'),
                    'brand' => $cardData['brand'] ?? 'Visa',
                    'expiry_month' => $cardData['expiry_month'],
                    'expiry_year' => $cardData['expiry_year'],
                    'card_balance' => $fundingAmountUsd,
                    'last4' => $cardData['last4'],
                    'status' => 'active',
                    'full_response_json' => json_encode($result),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('transaction')->where('transid', $transId)->update(['status' => 'completed']);

                // 4. Log to message table for Admin Card Transactions
                DB::table('message')->insert([
                    'username' => $user->username,
                    'amount' => $totalCostNgn,
                    'message' => "Created USD Virtual Card ({$providerName}) | Fees Included",
                    'oldbal' => $user->bal,
                    'newbal' => $user->bal - $totalCostNgn,
                    'habukhan_date' => $this->system_date(),
                    'plan_status' => 1,
                    'transid' => $transId,
                    'role' => 'dollar_card'
                ]);

                DB::commit();

                return response()->json(['status' => 'success', 'message' => 'Dollar Card created successfully'], 201);
            } else {
                throw new \Exception($result['message'] ?? 'Provider creation failed');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Dollar Card Creation Failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getCard(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        $settings = $this->getCardSettings();
        $providerName = $settings->dollar_card_provider ?? 'sudo';

        $card = DB::table('virtual_cards')
            ->where('user_id', $userId)
            ->whereNotIn('status', ['terminated', 'canceled'])
            ->orderBy('created_at', 'desc')
            ->first();

        // Use the card's provider if it exists, otherwise the default from settings
        $effectiveProvider = $card ? $card->provider : $providerName;

        $rspSettings = [
            'dollar_rate' => (float) $this->getActiveRate($settings, 'buy'),
            'funding_fee_percent' => (float) (($effectiveProvider === 'xixapay') ? ($settings->xixapay_funding_fee_percent ?? 1.5) : ($settings->sudo_funding_fee_percent ?? 1.5)),
            'withdrawal_fee_percent' => (float) (($effectiveProvider === 'xixapay') ? ($settings->xixapay_withdrawal_fee_percent ?? 1.5) : ($settings->sudo_withdrawal_fee_percent ?? 1.5)),
            'creation_fee_usd' => (float) (($effectiveProvider === 'xixapay') ? ($settings->xixapay_creation_fee ?? 5.00) : ($settings->sudo_creation_fee ?? 5.00)),
            'card_lock' => (int) (($settings->card_lock ?? 0) || ($settings->sudo_card_lock ?? 0)),
            'provider' => $effectiveProvider
        ];

        if (!$card) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'has_card' => false,
                'has_customer_id' => ($effectiveProvider === 'sudo') ? !empty($user->sudo_customer_id) : !empty($user->customer_id),
                'kyc_verified' => ($user->kyc ?? '0') == '1',
                'settings' => $rspSettings,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'has_card' => true,
            'has_customer_id' => true,
            'kyc_verified' => ($user->kyc ?? '0') == '1',
            'data' => [
                'id' => $card->id,
                'card_id' => $card->card_id,
                'masked_pan' => $card->masked_pan,
                'brand' => $card->brand,
                'expiry_month' => $card->expiry_month,
                'expiry_year' => $card->expiry_year,
                'last4' => $card->last4,
                'balance' => (float) $card->card_balance,
                'status' => $card->status,
                'created_at' => $card->created_at,
                'provider' => $card->provider,
            ],
            'settings' => $rspSettings,
        ]);
    }

    public function getCardDetails(Request $request, $id)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $card = DB::table('virtual_cards')->where('id', $id)->where('user_id', $userId)->first();
        if (!$card)
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $provider = $this->getProvider($card->provider);
        $result = $provider->getCardDetails($card->card_id);

        if (isset($result['status']) && $result['status'] === 'success') {
            $remoteBalance = (float) ($result['data']['balance'] ?? $result['data']['card_balance'] ?? 0);
            DB::table('virtual_cards')->where('id', $id)->update(['card_balance' => $remoteBalance]);

            return response()->json([
                'status' => 'success',
                'data' => $result['data']
            ]);
        }

        return response()->json(['status' => 'error', 'message' => $result['message'] ?? 'Failed to reveal card details'], 400);
    }

    public function fundCard(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'pin' => 'required|numeric|digits:4'
        ]);
        if ($validator->fails())
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        $user = DB::table('user')->where('id', $userId)->first();
        if ($request->pin != $user->pin)
            return response()->json(['status' => 'error', 'message' => 'Invalid PIN'], 403);

        $settings = $this->getCardSettings();
        if (($settings->card_lock ?? 0) == 1 || ($settings->sudo_card_lock ?? 0) == 1) {
            return response()->json(['status' => 'error', 'message' => 'Dollar Card service is currently unavailable'], 403);
        }

        $card = DB::table('virtual_cards')->where('id', $request->card_id)->where('user_id', $userId)->first();
        if (!$card)
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $fundingAmountUsd = (float) $request->amount;
        $dollarRate = $this->getActiveRate($settings, 'buy');
        $providerName = $card->provider;
        $fundingFeePercent = ($providerName === 'xixapay') ? ($settings->xixapay_funding_fee_percent ?? 1.5) : ($settings->sudo_funding_fee_percent ?? 1.5);

        $baseCostNgn = $fundingAmountUsd * $dollarRate;
        $feeNgn = $baseCostNgn * ($fundingFeePercent / 100);
        $totalCostNgn = $baseCostNgn + $feeNgn;

        if ($user->bal < $totalCostNgn)
            return response()->json(['status' => 'error', 'message' => 'Insufficient wallet balance'], 400);

        try {
            DB::beginTransaction();
            $transId = 'CARD_FUND_' . strtoupper(bin2hex(random_bytes(4)));

            DB::table('user')->where('id', $userId)->decrement('bal', $totalCostNgn);
            DB::table('transaction')->insert([
                'username' => $user->username,
                'transid' => $transId,
                'type' => 'Dollar Card Funding',
                'amount' => $totalCostNgn,
                'oldbal' => $user->bal,
                'newbal' => $user->bal - $totalCostNgn,
                'status' => 'processing',
                'date' => now(),
            ]);

            $provider = $this->getProvider($card->provider);
            $result = $provider->fundVirtualCard($card->card_id, $fundingAmountUsd);

            if (isset($result['status']) && $result['status'] === 'success') {
                DB::table('virtual_cards')->where('id', $card->id)->increment('card_balance', $fundingAmountUsd);
                DB::table('transaction')->where('transid', $transId)->update(['status' => 'completed']);

                // Log to message table for Admin Card Transactions
                DB::table('message')->insert([
                    'username' => $user->username,
                    'amount' => $totalCostNgn,
                    'message' => "Funded Virtual Card ({$providerName}) | Fees Included",
                    'oldbal' => $user->bal,
                    'newbal' => $user->bal - $totalCostNgn,
                    'habukhan_date' => $this->system_date(),
                    'plan_status' => 1,
                    'transid' => $transId,
                    'role' => 'dollar_card'
                ]);

                DB::commit();
                return response()->json(['status' => 'success', 'message' => 'Card funded successfully']);
            } else {
                throw new \Exception($result['message'] ?? 'Funding failed');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function withdrawCard(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'pin' => 'required|numeric|digits:4'
        ]);
        if ($validator->fails())
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        $user = DB::table('user')->where('id', $userId)->first();
        if ($request->pin != $user->pin)
            return response()->json(['status' => 'error', 'message' => 'Invalid PIN'], 403);

        $card = DB::table('virtual_cards')->where('id', $request->card_id)->where('user_id', $userId)->first();
        if (!$card)
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $settings = $this->getCardSettings();
        $withdrawAmountUsd = (float) $request->amount;

        $provider = $this->getProvider($card->provider);
        $details = $provider->getCardDetails($card->card_id);
        $currentBalance = (float) ($details['data']['balance'] ?? $details['data']['card_balance'] ?? 0);

        if ($currentBalance < $withdrawAmountUsd)
            return response()->json(['status' => 'error', 'message' => 'Insufficient card balance'], 400);

        $dollarRate = $this->getActiveRate($settings, 'sell');
        $providerName = $card->provider;
        $withdrawalFeePercent = ($providerName === 'xixapay') ? ($settings->xixapay_withdrawal_fee_percent ?? 1.5) : ($settings->sudo_withdrawal_fee_percent ?? 1.5);

        $baseCreditNgn = $withdrawAmountUsd * $dollarRate;
        $feeNgn = $baseCreditNgn * ($withdrawalFeePercent / 100);
        $totalCreditNgn = $baseCreditNgn - $feeNgn;

        try {
            DB::beginTransaction();
            $transId = 'CARD_WITHDRAW_' . strtoupper(bin2hex(random_bytes(4)));

            $result = $provider->withdrawVirtualCard($card->card_id, $withdrawAmountUsd);

            if (isset($result['status']) && $result['status'] === 'success') {
                DB::table('user')->where('id', $userId)->increment('bal', $totalCreditNgn);
                DB::table('virtual_cards')->where('id', $card->id)->decrement('card_balance', $withdrawAmountUsd);

                DB::table('transaction')->insert([
                    'username' => $user->username,
                    'transid' => $transId,
                    'type' => 'Dollar Card Withdrawal',
                    'amount' => $totalCreditNgn,
                    'oldbal' => $user->bal,
                    'newbal' => $user->bal + $totalCreditNgn,
                    'status' => 'completed',
                    'date' => now(),
                ]);

                // Log to message table for Admin Card Transactions
                DB::table('message')->insert([
                    'username' => $user->username,
                    'amount' => $totalCreditNgn,
                    'message' => "Withdrew from Virtual Card ({$providerName}) | Fees Deducted",
                    'oldbal' => $user->bal,
                    'newbal' => $user->bal + $totalCreditNgn,
                    'habukhan_date' => $this->system_date(),
                    'plan_status' => 1,
                    'transid' => $transId,
                    'role' => 'dollar_card'
                ]);

                DB::commit();
                return response()->json(['status' => 'success', 'message' => 'Funds withdrawn to wallet successfully']);
            } else {
                throw new \Exception($result['message'] ?? 'Withdrawal failed');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function changeCardStatus(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'status' => 'required|in:freeze,unfreeze'
        ]);
        if ($validator->fails())
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        $card = DB::table('virtual_cards')->where('id', $request->card_id)->where('user_id', $userId)->first();
        if (!$card)
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $provider = $this->getProvider($card->provider);
        $action = $request->status;
        $result = $provider->changeCardStatus($card->card_id, $action === 'freeze' ? 'frozen' : 'active');

        if (isset($result['status']) && $result['status'] === 'success') {
            DB::table('virtual_cards')->where('id', $card->id)->update(['status' => $action === 'freeze' ? 'frozen' : 'active']);
            return response()->json(['status' => 'success', 'message' => 'Card status updated']);
        }

        return response()->json(['status' => 'error', 'message' => $result['message'] ?? 'Failed to update card status'], 400);
    }

    public function terminateCard(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'pin' => 'required|numeric|digits:4'
        ]);
        if ($validator->fails())
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        $user = DB::table('user')->where('id', $userId)->first();
        if ($request->pin != $user->pin)
            return response()->json(['status' => 'error', 'message' => 'Invalid PIN'], 403);

        $card = DB::table('virtual_cards')->where('id', $request->card_id)->where('user_id', $userId)->first();
        if (!$card)
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        try {
            $provider = $this->getProvider($card->provider);
            $details = $provider->getCardDetails($card->card_id);
            $refundAmountUsd = (float) ($details['data']['balance'] ?? $details['data']['card_balance'] ?? 0);

            $result = $provider->terminateVirtualCard($card->card_id);

            if (isset($result['status']) && $result['status'] === 'success') {
                DB::beginTransaction();
                DB::table('virtual_cards')->where('id', $card->id)->update(['status' => 'terminated', 'card_balance' => 0]);

                if ($refundAmountUsd > 0) {
                    $settings = $this->getCardSettings();
                    $dollarRate = $this->getActiveRate($settings, 'sell');
                    $refundNgn = $refundAmountUsd * $dollarRate;

                    DB::table('user')->where('id', $userId)->increment('bal', $refundNgn);
                    DB::table('transaction')->insert([
                        'username' => $user->username,
                        'transid' => 'CARD_REFUND_' . strtoupper(bin2hex(random_bytes(4))),
                        'type' => 'Dollar Card Termination Refund',
                        'amount' => $refundNgn,
                        'oldbal' => $user->bal,
                        'newbal' => $user->bal + $refundNgn,
                        'status' => 'completed',
                        'date' => now(),
                    ]);

                    // Log to message table for Admin Card Transactions
                    DB::table('message')->insert([
                        'username' => $user->username,
                        'amount' => $refundNgn,
                        'message' => "Terminated Virtual Card ({$card->provider}) | Balance Refunded",
                        'oldbal' => $user->bal,
                        'newbal' => $user->bal + $refundNgn,
                        'habukhan_date' => $this->system_date(),
                        'plan_status' => 1,
                        'transid' => 'CARD_TERM_' . strtoupper(bin2hex(random_bytes(4))),
                        'role' => 'dollar_card'
                    ]);
                }
                DB::commit();
                return response()->json(['status' => 'success', 'message' => 'Card terminated successfully' . ($refundAmountUsd > 0 ? " and \${$refundAmountUsd} refunded to wallet." : "")]);
            } else {
                throw new \Exception($result['message'] ?? 'Termination failed');
            }
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0)
                DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function getTransactions(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId)
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $card = DB::table('virtual_cards')->where('user_id', $userId)->whereNotIn('status', ['terminated', 'canceled'])->first();
        if (!$card)
            return response()->json(['status' => 'success', 'data' => []]);

        $provider = $this->getProvider($card->provider);
        $result = $provider->getTransactions($card->card_id);

        if (isset($result['status']) && $result['status'] === 'success') {
            return response()->json(['status' => 'success', 'data' => $result['data']]);
        }

        return response()->json(['status' => 'error', 'message' => 'Failed to fetch transactions'], 400);
    }

    public function handleWebhook(Request $request)
    {
        $provider = $this->getProvider();
        return $provider->handleWebhook($request);
    }

    /**
     * Admin: Get Dollar Card Settings
     */
    public function adminGetSettings(Request $request, $id)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $settings = $this->getCardSettings();
        return response()->json(['status' => 'success', 'data' => $settings]);
    }

    /**
     * Admin: Update Dollar Card Settings
     */
    public function adminUpdateSettings(Request $request, $id)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $data = $request->only([
            'card_lock',
            'dollar_card_provider',
            'xixapay_manual_buy_rate',
            'xixapay_manual_sell_rate',
            'xixapay_funding_fee_percent',
            'xixapay_withdrawal_fee_percent',
            'xixapay_creation_fee',
            'sudo_card_lock',
            'sudo_dollar_rate',
            'sudo_manual_sell_rate',
            'sudo_rate_source',
            'sudo_creation_fee',
            'sudo_funding_fee_percent',
            'sudo_withdrawal_fee_percent',
            'sudo_failed_tx_fee',
            'sudo_max_daily_declines'
        ]);

        DB::table('card_settings')->where('id', 1)->update($data);

        return response()->json(['status' => 'success', 'message' => 'Settings updated successfully']);
    }

    /**
     * Admin: Get All Cards
     */
    public function adminGetAllCards(Request $request, $id)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $search = $request->search;
        $query = DB::table('virtual_cards')
            ->join('user', 'virtual_cards.user_id', '=', 'user.id')
            ->select('virtual_cards.*', 'user.username');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('user.username', 'like', "%$search%")
                    ->orWhere('virtual_cards.card_id', 'like', "%$search%");
            });
        }

        $total = $query->count();
        $cards = $query->orderBy('virtual_cards.created_at', 'desc')
            ->offset($request->page * $request->rowsPerPage)
            ->limit($request->rowsPerPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cards,
            'total' => $total
        ]);
    }

    /**
     * Admin: Terminate Card
     */
    public function adminTerminateCard(Request $request, $id, $cardId)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $card = DB::table('virtual_cards')->where('card_id', $cardId)->first();
        if (!$card) {
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);
        }

        $provider = $this->getProvider($card->provider);
        $result = $provider->terminateVirtualCard($cardId);

        if ($result['status'] === 'success') {
            DB::table('virtual_cards')->where('card_id', $cardId)->update(['status' => 'terminated']);
            return response()->json(['status' => 'success', 'message' => 'Card terminated successfully']);
        }

        return response()->json(['status' => 'error', 'message' => $result['message']], 400);
    }

    /**
     * Admin: Delete Card Record
     */
    public function adminDeleteCard(Request $request, $id, $cardId)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        DB::table('virtual_cards')->where('card_id', $cardId)->delete();
        return response()->json(['status' => 'success', 'message' => 'Card record deleted']);
    }

    /**
     * Admin: Get Card Info
     */
    public function adminGetCardInfo(Request $request, $id, $cardId)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $card = DB::table('virtual_cards')->where('card_id', $cardId)->first();
        if (!$card) {
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);
        }

        $user = DB::table('user')->where('id', $card->user_id)->first();
        $provider = $this->getProvider($card->provider);

        // Try to get provider details (balance etc)
        $providerDetails = null;
        try {
            $providerDetails = $provider->getCardDetails($cardId);
        } catch (\Exception $e) {
            Log::error("Failed to fetch provider details for card $cardId: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'card' => $card,
                'user' => [
                    'username' => $user->username,
                    'email' => $user->email,
                    'customer_id' => $card->sudo_customer_id
                ],
                'provider_details' => $providerDetails
            ]
        ]);
    }

    /**
     * Admin: Get All Dollar Card Customers
     */
    public function adminGetAllCustomers(Request $request, $id)
    {
        $userId = $this->verifytoken($id);
        if (!$userId || DB::table('user')->where(['id' => $userId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $search = $request->search;
        $query = DB::table('dollar_customers')
            ->join('user', 'dollar_customers.user_id', '=', 'user.id')
            ->select('dollar_customers.*', 'user.username as portal_username');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('user.username', 'like', "%$search%")
                    ->orWhere('dollar_customers.email', 'like', "%$search%")
                    ->orWhere('dollar_customers.customer_id', 'like', "%$search%");
            });
        }

        $total = $query->count();
        $customers = $query->orderBy('dollar_customers.created_at', 'desc')
            ->offset($request->page * $request->rowsPerPage)
            ->limit($request->rowsPerPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $customers,
            'total' => $total
        ]);
    }

    /**
     * Admin: Create Customer
     */
    public function adminCreateCustomer(Request $request, $id)
    {
        $adminId = $this->verifytoken($id);
        if (!$adminId || DB::table('user')->where(['id' => $adminId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|exists:user,username',
            'provider' => 'required|in:sudo,xixapay',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required',
            'address' => 'nullable',
            'id_type' => 'nullable',
            'id_number' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $user = DB::table('user')->where('username', $request->username)->first();

        // ── DUPLICATE GUARD ──────────────────────────────────────────────────
        // Check if this user already has a customer profile for this provider
        $existingCustomer = DB::table('dollar_customers')
            ->where('user_id', $user->id)
            ->where('provider', $request->provider)
            ->first();

        if ($existingCustomer && $existingCustomer->customer_id) {
            // Already exists — just update local info, skip live API call
            DB::table('dollar_customers')
                ->where('id', $existingCustomer->id)
                ->update([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'city' => $request->city,
                    'state' => $request->state,
                    'date_of_birth' => $request->date_of_birth,
                    'id_type' => $request->id_type,
                    'id_number' => $request->id_number,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer profile already exists. Local info updated.',
                'customer_id' => $existingCustomer->customer_id,
                'already_existed' => true,
            ]);
        }
        // ────────────────────────────────────────────────────────────────────

        try {
            $provider = $this->getProvider($request->provider);
            $result = $provider->createCustomer([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone,
                'address' => $request->address ?? '',
                'state' => $request->state ?? '',
                'city' => $request->city ?? '',
                'postal_code' => $request->postal_code ?? '100001',
                'date_of_birth' => $request->date_of_birth ?? '',
                'id_type' => $request->id_type ?? 'bvn',
                'id_number' => $request->id_number ?? '',
                // Files
                'id_card' => $request->file('id_card'),
                'utility_bill' => $request->file('utility_bill'),
                // Sudo specific
                'type' => 'individual',
                'status' => 'active'
            ]);

            if ($result['status'] === 'success') {
                $customerId = $result['customer_id'];
            } elseif (isset($result['message']) && str_contains(strtolower($result['message'] ?? ''), 'already exists')) {
                // Customer already exists on provider — treat as success, use existing local record or re-fetch
                $existing = DB::table('dollar_customers')
                    ->where('user_id', $user->id)
                    ->where('provider', $request->provider)
                    ->first();
                if ($existing && $existing->customer_id) {
                    $customerId = $existing->customer_id;
                } else {
                    throw new \Exception('Customer already exists on provider but no local record found. Please contact support.');
                }
            } else {
                throw new \Exception($result['message'] ?? 'Failed to create customer');
            }

            DB::table('dollar_customers')->updateOrInsert(
                ['user_id' => $user->id, 'provider' => $request->provider],
                [
                    'customer_id' => $customerId,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'city' => $request->city,
                    'state' => $request->state,
                    'date_of_birth' => $request->date_of_birth,
                    'id_type' => $request->id_type,
                    'id_number' => $request->id_number,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            return response()->json(['status' => 'success', 'message' => 'Customer created successfully', 'customer_id' => $customerId]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Admin: Create Card
     */
    public function adminCreateCard(Request $request, $id)
    {
        $adminId = $this->verifytoken($id);
        if (!$adminId || DB::table('user')->where(['id' => $adminId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|exists:user,username',
            'provider' => 'required|in:sudo,xixapay',
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:USD,NGN'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $user = DB::table('user')->where('username', $request->username)->first();
        $customer = DB::table('dollar_customers')
            ->where('user_id', $user->id)
            ->where('provider', $request->provider)
            ->first();

        if (!$customer || !$customer->customer_id) {
            return response()->json(['status' => 'error', 'message' => 'User does not have a customer profile for this provider. Create customer first.'], 400);
        }

        // ── DUPLICATE CARD GUARD ─────────────────────────────────────────────
        $existingCard = DB::table('virtual_cards')
            ->where('user_id', $user->id)
            ->where('provider', $request->provider)
            ->where('card_type', $request->currency)
            ->whereNotIn('status', ['terminated', 'canceled', 'blocked'])
            ->first();

        if ($existingCard) {
            return response()->json([
                'status' => 'error',
                'message' => "This user already has an active {$request->currency} card with {$request->provider} (Card ID: {$existingCard->card_id}). Fund the existing card instead of creating a new one.",
                'existing_card_id' => $existingCard->card_id,
            ], 400);
        }
        // ────────────────────────────────────────────────────────────────────

        try {
            $provider = $this->getProvider($request->provider);
            $result = $provider->createVirtualCard($customer->customer_id, $request->currency, (float) $request->amount);

            if ($result['status'] === 'success') {
                $cardData = $result['data'];
                DB::table('virtual_cards')->insert([
                    'user_id' => $user->id,
                    'provider' => $request->provider,
                    'card_id' => $result['card_id'],
                    'sudo_card_id' => $request->provider === 'sudo' ? $result['card_id'] : null,
                    'sudo_customer_id' => $request->provider === 'sudo' ? $customer->customer_id : null,
                    'masked_pan' => $cardData['maskedPan'] ?? $cardData['bin'] ?? null,
                    'brand' => $cardData['brand'] ?? 'Visa',
                    'card_balance' => (float) $request->amount,
                    'status' => 'active',
                    'card_type' => $request->currency,
                    'full_response_json' => json_encode($result),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json(['status' => 'success', 'message' => 'Card created successfully', 'card_id' => $result['card_id']]);
            } else {
                throw new \Exception($result['message'] ?? 'Failed to create card');
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Admin: Manual Fund (Credit)
     */
    public function adminFundCard(Request $request, $id)
    {
        $adminId = $this->verifytoken($id);
        if (!$adminId || DB::table('user')->where(['id' => $adminId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'amount' => 'required|numeric|min:0.1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $card = DB::table('virtual_cards')->where('card_id', $request->card_id)->first();
        if (!$card) {
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);
        }

        try {
            $provider = $this->getProvider($card->provider);
            $result = $provider->fundVirtualCard($card->card_id, (float) $request->amount);

            if ($result['status'] === 'success') {
                DB::table('virtual_cards')->where('id', $card->id)->increment('card_balance', (float) $request->amount);
                return response()->json(['status' => 'success', 'message' => 'Card credited successfully']);
            } else {
                throw new \Exception($result['message'] ?? 'Funding failed');
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Admin: Manual Withdraw
     */
    public function adminWithdrawCard(Request $request, $id)
    {
        $adminId = $this->verifytoken($id);
        if (!$adminId || DB::table('user')->where(['id' => $adminId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'amount' => 'required|numeric|min:0.1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $card = DB::table('virtual_cards')->where('card_id', $request->card_id)->first();
        if (!$card) {
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);
        }

        try {
            $provider = $this->getProvider($card->provider);
            $result = $provider->withdrawVirtualCard($card->card_id, (float) $request->amount);

            if ($result['status'] === 'success') {
                DB::table('virtual_cards')->where('id', $card->id)->decrement('card_balance', (float) $request->amount);
                return response()->json(['status' => 'success', 'message' => 'Card debited successfully']);
            } else {
                throw new \Exception($result['message'] ?? 'Withdrawal failed');
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Admin: Update Customer
     */
    public function adminUpdateCustomer(Request $request, $id)
    {
        $adminId = $this->verifytoken($id);
        if (!$adminId || DB::table('user')->where(['id' => $adminId, 'type' => 'ADMIN'])->count() == 0) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|exists:user,username',
            'provider' => 'required|in:sudo,xixapay',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required',
            'address' => 'nullable',
            'id_type' => 'nullable',
            'id_number' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $user = DB::table('user')->where('username', $request->username)->first();
        $customer = DB::table('dollar_customers')
            ->where('user_id', $user->id)
            ->where('provider', $request->provider)
            ->first();

        if (!$customer) {
            return response()->json(['status' => 'error', 'message' => 'Customer record not found'], 404);
        }

        try {
            $provider = $this->getProvider($request->provider);

            $payload = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone,
                'address' => $request->address,
                'id_type' => $request->id_type,
                'id_number' => $request->id_number,
                // For Sudo compat
                'name' => $request->first_name . ' ' . $request->last_name,
                'phone' => $request->phone,
            ];

            if ($request->provider === 'sudo') {
                $result = $provider->updateCustomer($customer->customer_id, $payload);
                $success = $result['success'] ?? false;
            } else {
                // Xixapay
                $payload['customer_id'] = $customer->customer_id;
                $result = $provider->updateCustomer($payload);
                $success = ($result['status'] === 'success');
            }

            if ($success) {
                DB::table('dollar_customers')
                    ->where('id', $customer->id)
                    ->update([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'address' => $request->address,
                        'id_type' => $request->id_type,
                        'id_number' => $request->id_number,
                        'updated_at' => now()
                    ]);

                return response()->json(['status' => 'success', 'message' => 'Customer updated successfully']);
            } else {
                throw new \Exception($result['message'] ?? 'Failed to update customer');
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
