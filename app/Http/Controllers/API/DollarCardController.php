<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SudoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DollarCardController extends Controller
{
    protected $sudo;

    public function __construct()
    {
        $this->sudo = new SudoService();
    }

    private function getCardSettings()
    {
        return DB::table('card_settings')->where('id', 1)->first();
    }

    // ─── USER: CREATE CARD ───────────────────────────────────────

    public function createCard(Request $request)
    {
        set_time_limit(120);
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'User not found'], 401);

        // Check lock
        $settings = $this->getCardSettings();
        if (($settings->sudo_card_lock ?? 0) == 1) {
            return response()->json(['status' => 'error', 'message' => 'Dollar Card service is currently unavailable'], 403);
        }

        // Check existing active card
        $existingCard = DB::table('virtual_cards')
            ->where('user_id', $user->id)
            ->where('provider', 'sudo')
            ->whereNotIn('status', ['terminated', 'canceled'])
            ->first();

        if ($existingCard) {
            return response()->json(['status' => 'error', 'message' => 'You already have an active dollar card'], 400);
        }

        $validator = Validator::make($request->all(), [
            'pin'    => 'required|numeric|digits:4',
            'amount' => 'required|numeric|min:3',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        if ($request->pin != $user->pin) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Transaction PIN'], 403);
        }

        // User-chosen initial funding amount (min $3 required by Sudo)
        $creationFeeUsd = (float) $request->amount;
        $dollarRate = $settings->sudo_dollar_rate ?? 1500;
        $fundingFeePercent = $settings->sudo_funding_fee_percent ?? 1.5;

        // Total NGN = initial USD load + service fee
        $baseCostNgn = $creationFeeUsd * $dollarRate;
        $feeNgn = $baseCostNgn * ($fundingFeePercent / 100);
        $creationFeeNgn = $baseCostNgn + $feeNgn;

        if ($user->bal < $creationFeeNgn) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient funds. Required: ₦' . number_format($creationFeeNgn, 2) . " (funds card with \${$creationFeeUsd})"], 400);
        }

        try {
            // Step 1: Create Sudo customer if not exists
            $sudoCustomerId = $user->sudo_customer_id;
            if (empty($sudoCustomerId)) {
                $nameParts = explode(' ', $user->name, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? $nameParts[0];

                $customerResult = $this->sudo->createCustomer([
                    'name'       => $user->name,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $user->email,
                    'phone'      => $user->phone,
                    'address'    => $user->address ?? '1 Lagos Street',
                    'city'       => $user->city ?? 'Lagos',
                    'state'      => $user->state ?? 'Lagos',
                    'postal_code'=> $user->postal_code ?? '100001',
                    'dob'        => $user->dob ?? null,
                    'bvn'        => $user->bvn ?? null,
                    'nin'        => $user->nin ?? null,
                ]);

                if (!$customerResult['success']) {
                    return response()->json(['status' => 'error', 'message' => 'Failed to create card profile: ' . ($customerResult['message'] ?? 'Unknown error')], 400);
                }

                $sudoCustomerId = $customerResult['customer_id'];
                DB::table('user')->where('id', $user->id)->update(['sudo_customer_id' => $sudoCustomerId]);
            }

            // Step 2: Sync latest user data to Sudo customer (phone, email, BVN/NIN)
            // This ensures Sudo has all required identity info before card creation
            $updateResult = $this->sudo->updateCustomer($sudoCustomerId, [
                'name'        => $user->name,
                'phone'       => $user->phone,
                'email'       => $user->email,
                'dob'         => $user->dob ?? null,
                'address'     => $user->address ?? '1 Lagos Street',
                'city'        => $user->city ?? 'Lagos',
                'state'       => $user->state ?? 'Lagos',
                'postal_code' => $user->postal_code ?? '100001',
                'bvn'         => $user->bvn ?? null,
                'nin'         => $user->nin ?? null,
            ]);
            if (!$updateResult['success']) {
                return response()->json(['status' => 'error', 'message' => 'Failed to sync profile with card provider: ' . ($updateResult['message'] ?? 'Unknown error')], 400);
            }

            // Step 3: Create virtual USD card (amount = initial funding, min $3 required by Sudo)
            $cardResult = $this->sudo->createVirtualCard($sudoCustomerId, $creationFeeUsd);
            if (!$cardResult['success']) {
                return response()->json(['status' => 'error', 'message' => 'Failed to create card: ' . ($cardResult['message'] ?? 'Unknown error')], 400);
            }

            // Step 4: Debit from user wallet
            DB::table('user')->where('id', $user->id)->decrement('bal', $creationFeeNgn);

            // Step 5: Save card locally
            // Sudo sandbox returns balance: 0 on creation — always use our funded amount
            $initialBalance = $creationFeeUsd;
            DB::table('virtual_cards')->insert([
                'user_id'           => $user->id,
                'provider'          => 'sudo',
                'card_id'           => $cardResult['card_id'],
                'sudo_card_id'      => $cardResult['card_id'],
                'sudo_customer_id'  => $sudoCustomerId,
                'card_type'         => 'USD',
                'status'            => 'active',
                'masked_pan'        => $cardResult['masked_pan'],
                'brand'             => $cardResult['brand'],
                'expiry_month'      => $cardResult['expiry_month'],
                'expiry_year'       => $cardResult['expiry_year'],
                'last4'             => $cardResult['last4'],
                'card_balance'      => $initialBalance,
                'full_response_json'=> json_encode($cardResult['data']),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Step 6: Log transaction
            DB::table('message')->insert([
                'username'      => $user->username,
                'amount'        => $creationFeeNgn,
                'message'       => "Created USD Virtual Dollar Card | Initial load: \${$creationFeeUsd} | Fee: ₦" . number_format($feeNgn, 2),
                'oldbal'        => $user->bal,
                'newbal'        => $user->bal - $creationFeeNgn,
                'habukhan_date' => $this->system_date(),
                'plan_status'   => 1,
                'transid'       => $this->purchase_ref('DC_CREATE_'),
                'role'          => 'dollar_card',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Dollar Card created successfully',
                'data'    => [
                    'card_id'      => $cardResult['card_id'],
                    'masked_pan'   => $cardResult['masked_pan'],
                    'brand'        => $cardResult['brand'],
                    'expiry_month' => $cardResult['expiry_month'],
                    'expiry_year'  => $cardResult['expiry_year'],
                    'last4'        => $cardResult['last4'],
                    'balance'      => $initialBalance,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('DollarCard createCard error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Service error. Please try again.'], 500);
        }
    }

    // ─── USER: GET CARD ──────────────────────────────────────────

    public function getCard(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        $settings = $this->getCardSettings();

        $card = DB::table('virtual_cards')
            ->where('user_id', $userId)
            ->where('provider', 'sudo')
            ->whereNotIn('status', ['terminated', 'canceled'])
            ->first();

        if (!$card) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'has_card' => false,
                'has_customer_id' => !empty($user->sudo_customer_id),
                'kyc_verified' => ($user->kyc ?? '0') == '1',
                'settings' => [
                    'dollar_rate'            => (float) ($settings->sudo_dollar_rate ?? 1500),
                    'funding_fee_percent'    => (float) ($settings->sudo_funding_fee_percent ?? 1.5),
                    'withdrawal_fee_percent' => (float) ($settings->sudo_withdrawal_fee_percent ?? 1.5),
                    'creation_fee_usd'       => max((float)($settings->sudo_creation_fee ?? 5.00), 3.0),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'has_card' => true,
            'has_customer_id' => true,
            'kyc_verified' => ($user->kyc ?? '0') == '1',
            'data' => [
                'id'           => $card->id,
                'card_id'      => $card->sudo_card_id ?? $card->card_id,
                'masked_pan'   => $card->masked_pan,
                'brand'        => $card->brand,
                'expiry_month' => $card->expiry_month,
                'expiry_year'  => $card->expiry_year,
                'last4'        => $card->last4,
                'balance'      => (float) $card->card_balance,
                'status'       => $card->status,
                'created_at'   => $card->created_at,
            ],
            'settings' => [
                'dollar_rate'            => (float) ($settings->sudo_dollar_rate ?? 1500),
                'funding_fee_percent'    => (float) ($settings->sudo_funding_fee_percent ?? 1.5),
                'withdrawal_fee_percent' => (float) ($settings->sudo_withdrawal_fee_percent ?? 1.5),
                'creation_fee_usd'       => max((float)($settings->sudo_creation_fee ?? 5.00), 3.0),
            ],
        ]);
    }

    // ─── USER: GET CARD DETAILS (with sensitive data) ────────────

    public function getCardDetails(Request $request, $id)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $card = DB::table('virtual_cards')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->where('provider', 'sudo')
            ->first();

        if (!$card) return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $result = $this->sudo->getCardDetails($card->sudo_card_id ?? $card->card_id, true);
        if (!$result['success']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], 400);
        }

        $cardData = $result['data'];
        return response()->json([
            'status' => 'success',
            'data' => [
                'card_number' => $cardData['number'] ?? $cardData['maskedPan'] ?? $card->masked_pan,
                'cvv' => $cardData['cvv2'] ?? '***',
                'expiry_month' => $cardData['expiryMonth'] ?? $card->expiry_month,
                'expiry_year' => $cardData['expiryYear'] ?? $card->expiry_year,
                'brand' => $cardData['brand'] ?? $card->brand,
                'name' => $cardData['customer']['name'] ?? null,
                'balance' => (float) $card->card_balance,
                'status' => $card->status,
            ],
        ]);
    }

    // ─── USER: FUND CARD ─────────────────────────────────────────

    public function fundCard(Request $request)
    {
        set_time_limit(120);
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'User not found'], 401);

        $settings = $this->getCardSettings();
        if (($settings->sudo_card_lock ?? 0) == 1) {
            return response()->json(['status' => 'error', 'message' => 'Dollar Card service is currently unavailable'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1', // USD amount
            'pin' => 'required|numeric|digits:4',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        if ($request->pin != $user->pin) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Transaction PIN'], 403);
        }

        $card = DB::table('virtual_cards')
            ->where('user_id', $user->id)
            ->where('provider', 'sudo')
            ->where('status', 'active')
            ->first();

        if (!$card) return response()->json(['status' => 'error', 'message' => 'No active dollar card found'], 404);

        $amountUsd = (float) $request->amount;
        $dollarRate = $settings->sudo_dollar_rate ?? 1500;
        $fundingFeePercent = $settings->sudo_funding_fee_percent ?? 1.5;

        $baseCostNgn = $amountUsd * $dollarRate;
        $feeNgn = $baseCostNgn * ($fundingFeePercent / 100);
        $totalDebitNgn = $baseCostNgn + $feeNgn;

        if ($user->bal < $totalDebitNgn) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient funds. Required: ₦' . number_format($totalDebitNgn, 2)], 400);
        }

        try {
            // Fund on Sudo
            $result = $this->sudo->fundCard($card->sudo_card_id ?? $card->card_id, $amountUsd);
            if (!$result['success']) {
                return response()->json(['status' => 'error', 'message' => 'Funding failed: ' . ($result['message'] ?? 'Unknown error')], 400);
            }

            // Debit wallet
            DB::table('user')->where('id', $user->id)->decrement('bal', $totalDebitNgn);

            // Update local balance
            DB::table('virtual_cards')->where('id', $card->id)->increment('card_balance', $amountUsd);

            // Log transaction
            DB::table('message')->insert([
                'username' => $user->username,
                'amount' => $totalDebitNgn,
                'message' => "Funded Dollar Card \${$amountUsd} | Rate: ₦{$dollarRate}/\$1 | Fee: " . number_format($feeNgn, 2),
                'oldbal' => $user->bal,
                'newbal' => $user->bal - $totalDebitNgn,
                'habukhan_date' => $this->system_date(),
                'plan_status' => 1,
                'transid' => $this->purchase_ref('DC_FUND_'),
                'role' => 'dollar_card',
            ]);

            $newBalance = (float) $card->card_balance + $amountUsd;

            return response()->json([
                'status' => 'success',
                'message' => "Card funded with \${$amountUsd} successfully",
                'data' => [
                    'amount_usd' => $amountUsd,
                    'amount_ngn' => $totalDebitNgn,
                    'fee_ngn' => $feeNgn,
                    'new_balance' => $newBalance,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('DollarCard fundCard error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Service error'], 500);
        }
    }

    // ─── USER: WITHDRAW FROM CARD ────────────────────────────────

    public function withdrawCard(Request $request)
    {
        set_time_limit(120);
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'User not found'], 401);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'pin' => 'required|numeric|digits:4',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        if ($request->pin != $user->pin) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Transaction PIN'], 403);
        }

        $card = DB::table('virtual_cards')
            ->where('user_id', $user->id)
            ->where('provider', 'sudo')
            ->where('status', 'active')
            ->first();

        if (!$card) return response()->json(['status' => 'error', 'message' => 'No active dollar card found'], 404);

        $amountUsd = (float) $request->amount;
        if ($amountUsd > $card->card_balance) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient card balance. Available: $' . number_format($card->card_balance, 2)], 400);
        }

        $settings = $this->getCardSettings();
        $dollarRate = $settings->sudo_dollar_rate ?? 1500;
        $withdrawFeePercent = $settings->sudo_withdrawal_fee_percent ?? 1.5;

        $baseCreditNgn = $amountUsd * $dollarRate;
        $feeNgn = $baseCreditNgn * ($withdrawFeePercent / 100);
        $netCreditNgn = $baseCreditNgn - $feeNgn;

        try {
            $result = $this->sudo->withdrawFromCard($card->sudo_card_id ?? $card->card_id, $amountUsd);
            if (!$result['success']) {
                return response()->json(['status' => 'error', 'message' => 'Withdrawal failed: ' . ($result['message'] ?? 'Unknown error')], 400);
            }

            // Credit wallet
            DB::table('user')->where('id', $user->id)->increment('bal', $netCreditNgn);

            // Update local balance
            DB::table('virtual_cards')->where('id', $card->id)->decrement('card_balance', $amountUsd);

            DB::table('message')->insert([
                'username' => $user->username,
                'amount' => $netCreditNgn,
                'message' => "Withdrew \${$amountUsd} from Dollar Card | Rate: ₦{$dollarRate}/\$1 | Fee: ₦" . number_format($feeNgn, 2),
                'oldbal' => $user->bal,
                'newbal' => $user->bal + $netCreditNgn,
                'habukhan_date' => $this->system_date(),
                'plan_status' => 1,
                'transid' => $this->purchase_ref('DC_WITHDRAW_'),
                'role' => 'dollar_card',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Withdrew \${$amountUsd} successfully",
                'data' => [
                    'amount_usd' => $amountUsd,
                    'credit_ngn' => $netCreditNgn,
                    'fee_ngn' => $feeNgn,
                    'new_balance' => (float) $card->card_balance - $amountUsd,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('DollarCard withdrawCard error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Service error'], 500);
        }
    }

    // ─── USER: FREEZE / UNFREEZE ─────────────────────────────────

    public function changeCardStatus(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:freeze,unfreeze',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        $card = DB::table('virtual_cards')
            ->where('user_id', $userId)
            ->where('provider', 'sudo')
            ->whereNotIn('status', ['terminated', 'canceled'])
            ->first();

        if (!$card) return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $action = $request->action;
        $result = $action === 'freeze'
            ? $this->sudo->freezeCard($card->sudo_card_id ?? $card->card_id)
            : $this->sudo->unfreezeCard($card->sudo_card_id ?? $card->card_id);

        // Log Sudo response for debugging
        Log::info('Sudo changeCardStatus response', [
            'action'  => $action,
            'card_id' => $card->sudo_card_id ?? $card->card_id,
            'result'  => $result,
        ]);

        // Update local DB regardless — Sudo sandbox may show active but we track locally
        $newStatus = $action === 'freeze' ? 'frozen' : 'active';
        DB::table('virtual_cards')->where('id', $card->id)->update(['status' => $newStatus, 'updated_at' => now()]);

        $user = DB::table('user')->where('id', $userId)->first();
        DB::table('message')->insert([
            'username'      => $user->username,
            'amount'        => 0,
            'message'       => 'Dollar Card ' . ucfirst($action) . 'd',
            'oldbal'        => $user->bal,
            'newbal'        => $user->bal,
            'habukhan_date' => $this->system_date(),
            'plan_status'   => 1,
            'transid'       => $this->purchase_ref('DC_STATUS_'),
            'role'          => 'dollar_card',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Card ' . $action . 'd successfully', 'new_status' => $newStatus]);
    }

    // ─── USER: TERMINATE CARD ────────────────────────────────────

    public function terminateCard(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $validator = Validator::make($request->all(), [
            'pin' => 'required|numeric|digits:4',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);

        $user = DB::table('user')->where('id', $userId)->first();
        if ($request->pin != $user->pin) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Transaction PIN'], 403);
        }

        $card = DB::table('virtual_cards')
            ->where('user_id', $userId)
            ->where('provider', 'sudo')
            ->whereNotIn('status', ['terminated', 'canceled'])
            ->first();

        if (!$card) return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        // If card has balance, withdraw it first
        $refundNgn = 0;
        if ($card->card_balance > 0) {
            $settings = $this->getCardSettings();
            $dollarRate = $settings->sudo_dollar_rate ?? 1500;
            $refundNgn = $card->card_balance * $dollarRate;
            DB::table('user')->where('id', $user->id)->increment('bal', $refundNgn);
        }

        $result = $this->sudo->terminateCard($card->sudo_card_id ?? $card->card_id, 'lost');

        DB::table('virtual_cards')->where('id', $card->id)->update([
            'status' => 'terminated',
            'card_balance' => 0,
            'updated_at' => now(),
        ]);

        DB::table('message')->insert([
            'username' => $user->username,
            'amount' => $refundNgn,
            'message' => 'Dollar Card Terminated' . ($refundNgn > 0 ? " | Refund: ₦" . number_format($refundNgn, 2) : ''),
            'oldbal' => $user->bal,
            'newbal' => $user->bal + $refundNgn,
            'habukhan_date' => $this->system_date(),
            'plan_status' => 1,
            'transid' => $this->purchase_ref('DC_TERMINATE_'),
            'role' => 'dollar_card',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Card terminated' . ($refundNgn > 0 ? ". ₦" . number_format($refundNgn, 2) . " refunded to wallet." : ''),
        ]);
    }

    // ─── USER: CARD TRANSACTIONS ─────────────────────────────────

    public function getTransactions(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $user = DB::table('user')->where('id', $userId)->first();
        if (!$user) return response()->json(['status' => 'success', 'data' => []]);

        $card = DB::table('virtual_cards')
            ->where('user_id', $userId)
            ->where('provider', 'sudo')
            ->first();

        // Always include local message table records for this user's dollar card activity
        $localMessages = DB::table('message')
            ->where('username', $user->username)
            ->where('role', 'dollar_card')
            ->orderBy('habukhan_date', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                return [
                    'source'      => 'local',
                    'type'        => 'local',
                    'description' => $row->message,
                    'amount'      => (float) $row->amount,
                    'currency'    => 'NGN',
                    'status'      => $row->plan_status == 1 ? 'completed' : ($row->plan_status == 2 ? 'failed' : 'pending'),
                    'date'        => $row->habukhan_date,
                    'transid'     => $row->transid,
                ];
            })->toArray();

        if (!$card) {
            return response()->json([
                'status' => 'success',
                'data'   => $localMessages,
            ]);
        }

        // Get from Sudo API
        $page   = $request->get('page', 0);
        $limit  = $request->get('limit', 25);
        $result = $this->sudo->getCardTransactions($card->sudo_card_id ?? $card->card_id, $page, $limit);

        $sudoTxns = collect($result['data'] ?? [])->map(function ($tx) {
            return [
                'source'      => 'sudo',
                'type'        => $tx['type'] ?? 'transaction',
                'description' => $tx['merchant']['name'] ?? ($tx['narration'] ?? 'Card Transaction'),
                'amount'      => (float) abs($tx['amount'] ?? 0),
                'currency'    => $tx['currency'] ?? 'USD',
                'status'      => $tx['status'] ?? 'completed',
                'date'        => $tx['createdAt'] ?? $tx['updatedAt'] ?? null,
                'transid'     => $tx['_id'] ?? null,
            ];
        })->toArray();

        // Merge: local messages first (most relevant), then Sudo API transactions
        $merged = array_merge($localMessages, $sudoTxns);

        return response()->json([
            'status'     => 'success',
            'data'       => $merged,
            'pagination' => $result['pagination'] ?? null,
        ]);
    }

    // ─── USER: GET SETTINGS (dollar rate, fees) ──────────────────

    public function getSettings(Request $request)
    {
        $userId = $this->verifyapptoken($request->header('Authorization'));
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);

        $settings = $this->getCardSettings();

        return response()->json([
            'status' => 'success',
            'data' => [
                'dollar_rate'            => (float) ($settings->sudo_dollar_rate ?? 1500),
                'creation_fee_usd'       => max((float)($settings->sudo_creation_fee ?? 5.00), 3.0),
                'funding_fee_percent'    => (float) ($settings->sudo_funding_fee_percent ?? 1.5),
                'withdrawal_fee_percent' => (float) ($settings->sudo_withdrawal_fee_percent ?? 1.5),
                'is_locked'              => (bool) ($settings->sudo_card_lock ?? 0),
            ],
        ]);
    }

    // ─── WEBHOOK: SUDO EVENTS ────────────────────────────────────

    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? 'unknown';

        Log::info('Sudo Webhook received', ['type' => $eventType]);

        // Log webhook
        $cardId = null;
        $amount = 0;
        $currency = 'USD';
        $merchantName = null;
        $merchantCategory = null;
        $channel = null;
        $userId = null;

        if ($eventType === 'authorization.request') {
            $obj = $payload['data']['object'] ?? [];
            $cardId = $obj['card']['_id'] ?? $obj['card'] ?? null;
            $amount = $obj['pendingRequest']['amount'] ?? 0;
            $currency = $obj['pendingRequest']['currency'] ?? 'USD';
            $merchantName = $obj['merchant']['name'] ?? null;
            $merchantCategory = $obj['merchant']['category'] ?? null;
            $channel = $obj['transactionMetadata']['channel'] ?? null;

            // Find user by card
            $localCard = DB::table('virtual_cards')->where('sudo_card_id', $cardId)->first();
            $userId = $localCard->user_id ?? null;

            // Log it
            DB::table('sudo_webhooks')->insert([
                'event_type' => $eventType,
                'card_id' => $cardId,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'approved',
                'merchant_name' => $merchantName,
                'merchant_category' => $merchantCategory,
                'channel' => $channel,
                'payload' => json_encode($payload),
                'response' => json_encode(['statusCode' => 200, 'data' => ['responseCode' => '00']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Auto-approve (we use gateway funding source)
            return response()->json([
                'statusCode' => 200,
                'data' => ['responseCode' => '00'],
            ]);
        }

        if ($eventType === 'card.balance') {
            $obj = $payload['data']['object'] ?? [];
            $cardId = $obj['_id'] ?? null;
            $localCard = DB::table('virtual_cards')->where('sudo_card_id', $cardId)->first();
            $balance = $localCard->card_balance ?? 0;

            return response()->json([
                'statusCode' => 200,
                'data' => ['responseCode' => '00', 'balance' => (float) $balance],
            ]);
        }

        if ($eventType === 'transaction.created') {
            $obj = $payload['data']['object'] ?? [];
            $cardId = $obj['card'] ?? null;
            $amount = abs($obj['amount'] ?? 0);
            $currency = $obj['currency'] ?? 'USD';
            $merchantName = $obj['merchant']['name'] ?? null;
            $channel = $obj['transactionMetadata']['channel'] ?? null;

            $localCard = DB::table('virtual_cards')->where('sudo_card_id', $cardId)->first();
            if ($localCard) {
                // Deduct from local balance
                DB::table('virtual_cards')->where('id', $localCard->id)->decrement('card_balance', $amount);
                $userId = $localCard->user_id;
            }

            DB::table('sudo_webhooks')->insert([
                'event_type' => $eventType,
                'card_id' => $cardId,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'merchant_name' => $merchantName,
                'channel' => $channel,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($eventType === 'transaction.refund') {
            $obj = $payload['data']['object'] ?? [];
            $cardId = $obj['card'] ?? null;
            $amount = abs($obj['merchantAmount'] ?? $obj['amount'] ?? 0);

            $localCard = DB::table('virtual_cards')->where('sudo_card_id', $cardId)->first();
            if ($localCard) {
                DB::table('virtual_cards')->where('id', $localCard->id)->increment('card_balance', $amount);
                $userId = $localCard->user_id;
            }

            DB::table('sudo_webhooks')->insert([
                'event_type' => $eventType,
                'card_id' => $cardId,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $obj['currency'] ?? 'USD',
                'status' => 'refunded',
                'merchant_name' => $obj['merchant']['name'] ?? null,
                'channel' => $obj['transactionMetadata']['channel'] ?? null,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($eventType === 'authorization.declined') {
            $obj = $payload['data']['object'] ?? [];
            $cardId = $obj['card'] ?? null;
            $reason = $obj['requestHistory'][0]['reason'] ?? 'unknown';
            $narration = $obj['requestHistory'][0]['narration'] ?? '';

            $localCard = DB::table('virtual_cards')->where('sudo_card_id', $cardId)->first();
            $userId = $localCard->user_id ?? null;

            DB::table('sudo_webhooks')->insert([
                'event_type' => $eventType,
                'card_id' => $cardId,
                'user_id' => $userId,
                'amount' => abs($obj['merchantAmount'] ?? 0),
                'currency' => $obj['currency'] ?? 'USD',
                'status' => 'declined',
                'merchant_name' => $reason . ': ' . $narration,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ─── FAILED TRANSACTION FEE ──────────────────────────
            if ($localCard && $userId) {
                $settings = $this->getCardSettings();
                $failedFee = (float) ($settings->sudo_failed_tx_fee ?? 0);

                if ($failedFee > 0 && $localCard->card_balance >= $failedFee) {
                    // Deduct fee from card balance
                    DB::table('virtual_cards')->where('id', $localCard->id)->decrement('card_balance', $failedFee);

                    // Log the fee in message table
                    $user = DB::table('user')->where('id', $userId)->first();
                    $dollarRate = (float) ($settings->sudo_dollar_rate ?? 1500);
                    $feeNgn = $failedFee * $dollarRate;

                    DB::table('message')->insert([
                        'username' => $user->username ?? '',
                        'message' => "Dollar Card declined transaction fee (\${$failedFee})",
                        'amount' => $feeNgn,
                        'oldbal' => $user->bal ?? 0,
                        'newbal' => $user->bal ?? 0,
                        'habukhan_date' => now()->format('Y-m-d H:i:s'),
                        'transid' => 'DC_FEE_' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'plan_status' => 1,
                        'role' => 'dollar_card',
                    ]);
                }

                // ─── AUTO-TERMINATE ON TOO MANY DECLINES ─────────
                $maxDeclines = (int) ($settings->sudo_max_daily_declines ?? 3);
                if ($maxDeclines > 0) {
                    $todayDeclines = DB::table('sudo_webhooks')
                        ->where('card_id', $cardId)
                        ->where('status', 'declined')
                        ->whereDate('created_at', now()->toDateString())
                        ->count();

                    if ($todayDeclines >= $maxDeclines && $localCard->status !== 'terminated') {
                        // Auto-terminate the card
                        try {
                            $this->sudo->terminateCard($localCard->sudo_card_id ?? $localCard->card_id);
                        } catch (\Exception $e) {
                            Log::error('Auto-terminate failed', ['error' => $e->getMessage()]);
                        }

                        // Refund remaining balance to user wallet
                        $refundNgn = 0;
                        $currentBalance = (float) DB::table('virtual_cards')->where('id', $localCard->id)->value('card_balance');
                        if ($currentBalance > 0) {
                            $dollarRate = (float) ($settings->sudo_dollar_rate ?? 1500);
                            $refundNgn = $currentBalance * $dollarRate;
                            DB::table('user')->where('id', $userId)->increment('bal', $refundNgn);
                        }

                        DB::table('virtual_cards')->where('id', $localCard->id)->update([
                            'status' => 'terminated',
                            'card_balance' => 0,
                            'updated_at' => now(),
                        ]);

                        // Log auto-termination
                        $user = $user ?? DB::table('user')->where('id', $userId)->first();
                        DB::table('message')->insert([
                            'username' => $user->username ?? '',
                            'message' => "Dollar Card auto-terminated ({$todayDeclines} declined transactions today). Balance refunded: ₦" . number_format($refundNgn, 2),
                            'amount' => $refundNgn,
                            'oldbal' => ($user->bal ?? 0) - $refundNgn,
                            'newbal' => $user->bal ?? 0,
                            'habukhan_date' => now()->format('Y-m-d H:i:s'),
                            'transid' => 'DC_AUTOTERM_' . strtoupper(substr(md5(uniqid()), 0, 8)),
                            'plan_status' => 1,
                            'role' => 'dollar_card',
                        ]);

                        Log::warning('Dollar card auto-terminated', [
                            'user_id' => $userId,
                            'card_id' => $cardId,
                            'declines_today' => $todayDeclines,
                        ]);
                    }
                }
            }
        }

        if ($eventType === 'card.terminated') {
            $cardData = $payload['data'] ?? [];
            $cardId = $cardData['_id'] ?? null;
            $localCard = DB::table('virtual_cards')->where('sudo_card_id', $cardId)->first();
            if ($localCard) {
                DB::table('virtual_cards')->where('id', $localCard->id)->update([
                    'status' => 'terminated',
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    // ─── ADMIN: GET SETTINGS ─────────────────────────────────────

    public function adminGetSettings($token)
    {
        $adminId = $this->verifytoken($token);
        if (!$adminId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        $admin = DB::table('user')->where('id', $adminId)->first();
        if (strtoupper($admin->type) !== 'ADMIN') return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);

        $settings = $this->getCardSettings();

        return response()->json([
            'status' => 'success',
            'data' => [
                'sudo_dollar_rate' => (float) ($settings->sudo_dollar_rate ?? 1500),
                'sudo_creation_fee' => (float) ($settings->sudo_creation_fee ?? 2),
                'sudo_funding_fee_percent' => (float) ($settings->sudo_funding_fee_percent ?? 1.5),
                'sudo_withdrawal_fee_percent' => (float) ($settings->sudo_withdrawal_fee_percent ?? 1.5),
                'sudo_card_lock' => (int) ($settings->sudo_card_lock ?? 0),
                'sudo_failed_tx_fee' => (float) ($settings->sudo_failed_tx_fee ?? 0.40),
                'sudo_max_daily_declines' => (int) ($settings->sudo_max_daily_declines ?? 3),
            ],
        ]);
    }

    // ─── ADMIN: UPDATE SETTINGS ──────────────────────────────────

    public function adminUpdateSettings(Request $request, $token)
    {
        $adminId = $this->verifytoken($token);
        if (!$adminId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        $admin = DB::table('user')->where('id', $adminId)->first();
        if (strtoupper($admin->type) !== 'ADMIN') return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);

        $update = [];
        if ($request->has('sudo_dollar_rate')) $update['sudo_dollar_rate'] = $request->sudo_dollar_rate;
        if ($request->has('sudo_creation_fee')) $update['sudo_creation_fee'] = $request->sudo_creation_fee;
        if ($request->has('sudo_funding_fee_percent')) $update['sudo_funding_fee_percent'] = $request->sudo_funding_fee_percent;
        if ($request->has('sudo_withdrawal_fee_percent')) $update['sudo_withdrawal_fee_percent'] = $request->sudo_withdrawal_fee_percent;
        if ($request->has('sudo_card_lock')) $update['sudo_card_lock'] = $request->sudo_card_lock;
        if ($request->has('sudo_failed_tx_fee')) $update['sudo_failed_tx_fee'] = $request->sudo_failed_tx_fee;
        if ($request->has('sudo_max_daily_declines')) $update['sudo_max_daily_declines'] = $request->sudo_max_daily_declines;

        if (!empty($update)) {
            DB::table('card_settings')->where('id', 1)->update($update);
        }

        return response()->json(['status' => 'success', 'message' => 'Dollar Card settings updated']);
    }

    // ─── ADMIN: ALL CARDS ────────────────────────────────────────

    public function adminGetAllCards(Request $request, $token)
    {
        $adminId = $this->verifytoken($token);
        if (!$adminId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        $admin = DB::table('user')->where('id', $adminId)->first();
        if (strtoupper($admin->type) !== 'ADMIN') return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);

        $page        = (int) $request->get('page', 0);
        $rowsPerPage = (int) $request->get('rowsPerPage', 10);
        $search      = $request->get('search', '');

        $query = DB::table('virtual_cards')
            ->where('virtual_cards.provider', 'sudo')
            ->join('user', 'virtual_cards.user_id', '=', 'user.id')
            ->select('virtual_cards.*', 'user.username', 'user.name as user_name', 'user.email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('user.username', 'like', "%{$search}%")
                  ->orWhere('virtual_cards.card_id', 'like', "%{$search}%")
                  ->orWhere('virtual_cards.masked_pan', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $cards = $query->orderBy('virtual_cards.created_at', 'desc')
            ->offset($page * $rowsPerPage)
            ->limit($rowsPerPage)
            ->get();

        return response()->json(['status' => 'success', 'data' => $cards, 'total' => $total]);
    }

    // ─── ADMIN: TERMINATE USER CARD ──────────────────────────────

    public function adminTerminateCard(Request $request, $cardId, $token)
    {
        $adminId = $this->verifytoken($token);
        if (!$adminId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        $admin = DB::table('user')->where('id', $adminId)->first();
        if (strtoupper($admin->type) !== 'ADMIN') return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);

        $card = DB::table('virtual_cards')->where('id', $cardId)->where('provider', 'sudo')->first();
        if (!$card) return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $this->sudo->terminateCard($card->sudo_card_id ?? $card->card_id);

        // Refund balance to user
        $refundNgn = 0;
        if ($card->card_balance > 0) {
            $settings = $this->getCardSettings();
            $dollarRate = $settings->sudo_dollar_rate ?? 1500;
            $refundNgn = $card->card_balance * $dollarRate;
            DB::table('user')->where('id', $card->user_id)->increment('bal', $refundNgn);
        }

        DB::table('virtual_cards')->where('id', $card->id)->update([
            'status' => 'terminated',
            'card_balance' => 0,
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Card terminated by admin. Refund: ₦' . number_format($refundNgn, 2)]);
    }

    // ─── ADMIN: DELETE CARD RECORD ───────────────────────────────

    public function adminDeleteCard(Request $request, $cardId, $token)
    {
        $adminId = $this->verifytoken($token);
        if (!$adminId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        $admin = DB::table('user')->where('id', $adminId)->first();
        if (strtoupper($admin->type) !== 'ADMIN') return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);

        $card = DB::table('virtual_cards')->where('id', $cardId)->first();
        if (!$card) return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        if (!in_array($card->status, ['terminated', 'canceled'])) {
            return response()->json(['status' => 'error', 'message' => 'Only terminated cards can be deleted'], 400);
        }

        DB::table('virtual_cards')->where('id', $cardId)->delete();
        return response()->json(['status' => 'success', 'message' => 'Card record deleted']);
    }

    // ─── ADMIN: CARD INFO ────────────────────────────────────────

    public function adminGetCardInfo(Request $request, $cardId, $token)
    {
        $adminId = $this->verifytoken($token);
        if (!$adminId) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        $admin = DB::table('user')->where('id', $adminId)->first();
        if (strtoupper($admin->type) !== 'ADMIN') return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);

        $card = DB::table('virtual_cards')->where('id', $cardId)->first();
        if (!$card) return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);

        $user = DB::table('user')->where('id', $card->user_id)->first();
        $sudoCard = $this->sudo->getCard($card->sudo_card_id ?? $card->card_id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'card'             => $card,
                'user'             => ['username' => $user->username, 'email' => $user->email, 'name' => $user->name, 'phone' => $user->phone],
                'provider_details' => $sudoCard['data'] ?? null,
            ],
        ]);
    }
}
