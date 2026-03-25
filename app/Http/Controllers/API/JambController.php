<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Purchase\ApiSending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class JambController extends Controller
{
    // ─── MOBILE: GET VARIATIONS (UTME types) ───

    public function getVariations(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        if (!$this->isJambEnabled()) {
            return response()->json(['status' => 'fail', 'message' => 'JAMB service is currently unavailable'], 503);
        }

        $settings = DB::table('settings')->first();
        $charge = (float) ($settings->jamb_discount ?? 0);

        // Fetch variations from VTpass API
        $other_api = DB::table('other_api')->first();
        if (!$other_api || !$other_api->vtpass_username || !$other_api->vtpass_password) {
            return response()->json(['status' => 'fail', 'message' => 'VTpass not configured'], 500);
        }

        $vtpass_token = base64_encode($other_api->vtpass_username . ":" . $other_api->vtpass_password);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://vtpass.com/api/service-variations?serviceID=jamb");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $vtpass_token,
            'Content-Type: application/json',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (empty($result) || !isset($result['content']['variations'])) {
            return response()->json(['status' => 'fail', 'message' => 'Unable to fetch JAMB variations'], 500);
        }

        $variations = [];
        foreach ($result['content']['variations'] as $v) {
            $amount = (float) $v['variation_amount'];
            $variations[] = [
                'variation_code' => $v['variation_code'],
                'name' => $v['name'],
                'amount' => $amount,
                'selling_price' => $this->applyCharge($amount, $charge),
                'fixed_price' => $v['fixedPrice'] === 'Yes',
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $variations,
            'charge' => $charge,
        ]);
    }

    // ─── MOBILE: VERIFY PROFILE ID ───

    public function verifyProfile(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'profile_id' => 'required|string',
            'variation_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $other_api = DB::table('other_api')->first();
        if (!$other_api || !$other_api->vtpass_username || !$other_api->vtpass_password) {
            return response()->json(['status' => 'fail', 'message' => 'VTpass not configured'], 500);
        }

        $vtpass_token = base64_encode($other_api->vtpass_username . ":" . $other_api->vtpass_password);

        $payload = [
            'billersCode' => $request->profile_id,
            'serviceID' => 'jamb',
            'type' => $request->variation_code,
        ];

        $endpoint = "https://vtpass.com/api/merchant-verify";
        $headers = [
            'Authorization: Basic ' . $vtpass_token,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        Log::info('JAMB Profile Verify:', ['payload' => $payload, 'response' => $result]);

        if (!empty($result) && isset($result['code']) && $result['code'] == '000') {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'customer_name' => $result['content']['Customer_Name'] ?? null,
                ],
            ]);
        }

        return response()->json([
            'status' => 'fail',
            'message' => $result['content']['error'] ?? 'Invalid Profile ID. Please check and try again.',
        ], 400);
    }

    // ─── MOBILE: PURCHASE JAMB PIN ───

    public function purchase(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        if (!$this->isJambEnabled()) {
            return response()->json(['status' => 'fail', 'message' => 'JAMB service is currently unavailable'], 503);
        }

        $validator = Validator::make($request->all(), [
            'profile_id' => 'required|string',
            'variation_code' => 'required|string',
            'phone' => 'required|string|min:10',
            'pin' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $user = DB::table('user')->where(['id' => $user_id, 'status' => 1])->first();
        if (!$user) {
            return response()->json(['status' => 'fail', 'message' => 'User not found'], 404);
        }

        if (trim($user->pin) != trim($request->pin)) {
            return response()->json(['status' => 'fail', 'message' => 'Invalid Transaction PIN'], 403);
        }

        // Determine price — fetch from VTpass API
        $other_api = DB::table('other_api')->first();
        if (!$other_api || !$other_api->vtpass_username || !$other_api->vtpass_password) {
            return response()->json(['status' => 'fail', 'message' => 'VTpass not configured'], 500);
        }

        $vtpass_token = base64_encode($other_api->vtpass_username . ":" . $other_api->vtpass_password);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://vtpass.com/api/service-variations?serviceID=jamb");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $vtpass_token,
            'Content-Type: application/json',
        ]);
        $varResponse = curl_exec($ch);
        curl_close($ch);
        $varResult = json_decode($varResponse, true);

        $basePrice = 0;
        $variationName = $request->variation_code;
        if (!empty($varResult['content']['variations'])) {
            foreach ($varResult['content']['variations'] as $v) {
                if ($v['variation_code'] === $request->variation_code) {
                    $basePrice = (float) $v['variation_amount'];
                    $variationName = $v['name'];
                    break;
                }
            }
        }

        if ($basePrice <= 0) {
            return response()->json(['status' => 'fail', 'message' => 'Unable to fetch JAMB price. Try again.'], 400);
        }

        $settings = DB::table('settings')->first();
        $charge = (float) ($settings->jamb_discount ?? 0);
        $sellingPrice = $this->applyCharge($basePrice, $charge);

        $transid = $this->purchase_ref('JAMB_');

        // Check duplicate
        if (DB::table('jamb_purchases')->where('transid', $transid)->exists() ||
            DB::table('message')->where('transid', $transid)->exists()) {
            return response()->json(['status' => 'fail', 'message' => 'Reference ID already used'], 400);
        }

        // Lock user row and check balance
        DB::beginTransaction();
        $user = DB::table('user')->where('id', $user_id)->lockForUpdate()->first();

        if ($user->bal < $sellingPrice) {
            DB::rollback();
            return response()->json([
                'status' => 'fail',
                'message' => 'Insufficient balance. You need ₦' . number_format($sellingPrice, 2),
            ], 400);
        }

        $oldBal = $user->bal;
        $newBal = $oldBal - $sellingPrice;

        DB::table('user')->where('id', $user_id)->update(['bal' => $newBal]);
        DB::commit();

        // Insert records
        $jambRecord = [
            'username' => $user->username,
            'profile_id' => $request->profile_id,
            'customer_name' => $request->customer_name ?? null,
            'variation_code' => $request->variation_code,
            'variation_name' => $variationName,
            'phone' => $request->phone,
            'amount' => $sellingPrice,
            'oldbal' => $oldBal,
            'newbal' => $newBal,
            'transid' => $transid,
            'plan_status' => 0,
            'plan_date' => $this->system_date(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $messageRecord = [
            'username' => $user->username,
            'amount' => $sellingPrice,
            'message' => '⏳ Processing JAMB ' . $variationName . '...',
            'oldbal' => $oldBal,
            'newbal' => $newBal,
            'habukhan_date' => $this->system_date(),
            'plan_status' => 0,
            'transid' => $transid,
            'role' => 'jamb',
            'service_type' => 'JAMB_PIN',
            'transaction_channel' => 'EXTERNAL',
        ];

        DB::table('jamb_purchases')->insert($jambRecord);
        DB::table('message')->insert($messageRecord);

        // Call VTpass
        $response = $this->callVtpassPurchase($request, $transid, $basePrice);

        if ($response === 'success') {
            $purchase = DB::table('jamb_purchases')->where('transid', $transid)->first();

            DB::table('jamb_purchases')->where('transid', $transid)->update(['plan_status' => 1]);
            DB::table('message')->where('transid', $transid)->update([
                'plan_status' => 1,
                'message' => '✅ JAMB ' . $variationName . ' purchased successfully. PIN: ' . ($purchase->purchased_code ?? 'See History'),
            ]);

            // Push notification
            try {
                \App\Helpers\NotificationHelper::sendTransactionNotification(
                    $user, 'debit', $sellingPrice,
                    'JAMB ' . $variationName,
                    $transid
                );
            } catch (\Exception $e) {
                Log::error('JAMB push notification failed', ['error' => $e->getMessage()]);
            }

            // Send JAMB PIN to customer email
            try {
                $purchasedCode = $purchase->purchased_code ?? '';
                // Clean up "Pin : " prefix for email display
                $cleanPin = $purchasedCode;
                if (stripos($cleanPin, 'Pin :') === 0) {
                    $cleanPin = trim(substr($cleanPin, 5));
                } elseif (stripos($cleanPin, 'Pin:') === 0) {
                    $cleanPin = trim(substr($cleanPin, 4));
                }

                $emailData = [
                    'email' => $user->email,
                    'username' => $user->username,
                    'title' => 'JAMB PIN - ' . $variationName . ' | ' . config('app.name'),
                    'variation_name' => $variationName,
                    'profile_id' => $request->profile_id,
                    'customer_name' => $request->customer_name ?? null,
                    'phone' => $request->phone,
                    'amount' => $sellingPrice,
                    'purchased_code' => $cleanPin,
                    'transid' => $transid,
                    'newbal' => $newBal,
                    'date' => $this->system_date(),
                ];
                $pdfData = array_merge($emailData, [
                    'invoice_type' => 'JAMB PIN INVOICE',
                    'reference' => $transid,
                    'status' => 'SUCCESSFUL',
                    'customer_name_student' => $request->customer_name ?? null,
                    'customer_email' => $user->email,
                    'customer_phone' => $request->phone,
                ]);
                $attachment = \App\Services\InvoiceService::generatePdf('JAMB_PIN', $pdfData);
                \App\Http\Controllers\MailController::send_mail($emailData, 'email.jamb_pin', $attachment);
            } catch (\Exception $e) {
                Log::error('JAMB email notification failed', ['error' => $e->getMessage()]);
            }

            $purchase = DB::table('jamb_purchases')->where('transid', $transid)->first();

            return response()->json([
                'status' => 'success',
                'message' => 'JAMB PIN purchased successfully',
                'data' => [
                    'transid' => $transid,
                    'variation' => $variationName,
                    'profile_id' => $request->profile_id,
                    'amount' => $sellingPrice,
                    'oldbal' => $oldBal,
                    'newbal' => $newBal,
                    'purchased_code' => $purchase->purchased_code,
                    'date' => $this->system_date(),
                ],
            ]);
        } elseif ($response === 'process') {
            return response()->json([
                'status' => 'process',
                'message' => 'JAMB PIN purchase is processing',
                'data' => [
                    'transid' => $transid,
                    'amount' => $sellingPrice,
                    'oldbal' => $oldBal,
                    'newbal' => $newBal,
                ],
            ]);
        } else {
            // Refund
            $refundBal = $newBal + $sellingPrice;
            DB::table('user')->where('id', $user_id)->update(['bal' => $refundBal]);
            DB::table('jamb_purchases')->where('transid', $transid)->update([
                'plan_status' => 2,
                'newbal' => $refundBal,
            ]);
            DB::table('message')->where('transid', $transid)->update([
                'plan_status' => 2,
                'newbal' => $refundBal,
                'message' => '❌ JAMB PIN purchase failed. Refunded ₦' . number_format($sellingPrice, 2),
            ]);

            return response()->json([
                'status' => 'fail',
                'message' => 'Transaction failed. Your wallet has been refunded.',
                'data' => [
                    'transid' => $transid,
                    'amount' => $sellingPrice,
                    'oldbal' => $oldBal,
                    'newbal' => $refundBal,
                ],
            ]);
        }
    }

    // ─── MOBILE: TRANSACTION HISTORY ───

    public function getHistory(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $user = DB::table('user')->where('id', $user_id)->first();
        if (!$user) {
            return response()->json(['status' => 'fail', 'message' => 'User not found'], 404);
        }

        $query = DB::table('jamb_purchases')->where('username', $user->username);

        if ($request->status && $request->status !== 'ALL') {
            $query->where('plan_status', $request->status);
        }

        $transactions = $query->orderBy('id', 'desc')->paginate($request->limit ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => $transactions,
        ]);
    }

    // ─── ADMIN: GET SETTINGS ───

    public function adminGetSettings(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $userId = $this->verifytoken($token);
        if (!$userId) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $admin = DB::table('user')->where(['id' => $userId, 'status' => 1, 'type' => 'ADMIN'])->first();
        if (!$admin) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $settings = DB::table('settings')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'jamb_status' => (int) ($settings->jamb_status ?? 1),
                'jamb_provider' => $settings->jamb_provider ?? 'vtpass',
                'jamb_discount' => (float) ($settings->jamb_discount ?? 0),
            ],
        ]);
    }

    // ─── ADMIN: UPDATE LOCK (enable/disable) ───

    public function adminUpdateLock(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $userId = $this->verifytoken($token);
        if (!$userId) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $admin = DB::table('user')->where(['id' => $userId, 'status' => 1, 'type' => 'ADMIN'])->first();
        if (!$admin) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'jamb_status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        DB::table('settings')->update(['jamb_status' => (int) $request->jamb_status]);

        return response()->json([
            'status' => 'success',
            'message' => $request->jamb_status == 1 ? 'JAMB service enabled' : 'JAMB service disabled',
        ]);
    }

    // ─── ADMIN: UPDATE PROVIDER SELECTION ───

    public function adminUpdateSelection(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $userId = $this->verifytoken($token);
        if (!$userId) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $admin = DB::table('user')->where(['id' => $userId, 'status' => 1, 'type' => 'ADMIN'])->first();
        if (!$admin) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'jamb_provider' => 'required|string|in:vtpass',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        DB::table('settings')->update(['jamb_provider' => $request->jamb_provider]);

        return response()->json([
            'status' => 'success',
            'message' => 'JAMB provider updated to ' . $request->jamb_provider,
        ]);
    }

    // ─── ADMIN: UPDATE DISCOUNT ───

    public function adminUpdateDiscount(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $userId = $this->verifytoken($token);
        if (!$userId) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $admin = DB::table('user')->where(['id' => $userId, 'status' => 1, 'type' => 'ADMIN'])->first();
        if (!$admin) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'jamb_discount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        DB::table('settings')->update(['jamb_discount' => (float) $request->jamb_discount]);

        return response()->json([
            'status' => 'success',
            'message' => 'JAMB charge fee updated to ₦' . $request->jamb_discount,
        ]);
    }

    // ─── ADMIN: GET TRANSACTIONS ───

    public function adminGetTransactions(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $userId = $this->verifytoken($token);
        if (!$userId) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $admin = DB::table('user')->where(['id' => $userId, 'status' => 1, 'type' => 'ADMIN'])->first();
        if (!$admin) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $search = $request->search;
        $query = DB::table('jamb_purchases');

        if ($request->status && $request->status !== 'ALL') {
            $query->where('plan_status', $request->status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('username', 'LIKE', "%$search%")
                  ->orWhere('profile_id', 'LIKE', "%$search%")
                  ->orWhere('customer_name', 'LIKE', "%$search%")
                  ->orWhere('variation_name', 'LIKE', "%$search%")
                  ->orWhere('phone', 'LIKE', "%$search%")
                  ->orWhere('transid', 'LIKE', "%$search%")
                  ->orWhere('purchased_code', 'LIKE', "%$search%")
                  ->orWhere('amount', 'LIKE', "%$search%");
            });
        }

        $transactions = $query->orderBy('id', 'desc')->paginate($request->limit ?? 20);

        return response()->json([
            'status' => 'success',
            'jamb_trans' => $transactions,
        ]);
    }

    // ─── ADMIN: REFUND / APPROVE TRANSACTION ───

    public function adminRefund(Request $request)
    {
        $token = $request->id ?? $request->route('id');
        $userId = $this->verifytoken($token);
        if (!$userId) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $admin = DB::table('user')->where(['id' => $userId, 'status' => 1])->where(function ($q) {
            $q->where('type', 'ADMIN')->orWhere('type', 'CUSTOMER');
        })->first();
        if (!$admin) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        if (!DB::table('jamb_purchases')->where('transid', $request->transid)->exists()) {
            return response()->json(['status' => 'fail', 'message' => 'Invalid Transaction ID'], 404);
        }

        $trans = DB::table('jamb_purchases')->where('transid', $request->transid)->first();
        $amount = $trans->amount;

        if ($request->plan_status == 1) {
            // Mark as success
            if ($trans->plan_status == 0) {
                DB::table('message')->where(['username' => $trans->username, 'transid' => $trans->transid])
                    ->update(['plan_status' => 1, 'message' => 'JAMB ' . $trans->variation_name . ' purchased successfully']);
                DB::table('jamb_purchases')->where('transid', $trans->transid)->update(['plan_status' => 1]);
            } elseif ($trans->plan_status == 2) {
                // Re-debit and mark success
                $b = DB::table('user')->where('username', $trans->username)->first();
                $bal = $b->bal;
                DB::table('user')->where('username', $trans->username)->update(['bal' => $bal - $amount]);
                DB::table('message')->where(['username' => $trans->username, 'transid' => $trans->transid])
                    ->update(['plan_status' => 1, 'message' => 'JAMB ' . $trans->variation_name . ' purchased successfully', 'oldbal' => $bal, 'newbal' => $bal - $amount]);
                DB::table('jamb_purchases')->where('transid', $trans->transid)
                    ->update(['plan_status' => 1, 'oldbal' => $bal, 'newbal' => $bal - $amount]);
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Cannot update this transaction'], 400);
            }
        } elseif ($request->plan_status == 2) {
            // Refund
            $b = DB::table('user')->where('username', $trans->username)->first();
            $bal = $b->bal;
            DB::table('user')->where('username', $trans->username)->update(['bal' => $bal + $amount]);
            DB::table('message')->where(['username' => $trans->username, 'transid' => $trans->transid])
                ->update(['plan_status' => 2, 'message' => 'JAMB Transaction Refunded - ' . $trans->variation_name, 'oldbal' => $bal, 'newbal' => $bal + $amount]);
            DB::table('jamb_purchases')->where('transid', $trans->transid)
                ->update(['plan_status' => 2, 'oldbal' => $bal, 'newbal' => $bal + $amount]);
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Invalid status'], 400);
        }

        return response()->json(['status' => 'success', 'message' => 'Transaction updated']);
    }

    // ─── PRIVATE: CALL VTPASS PURCHASE ───

    private function callVtpassPurchase(Request $request, string $transid, float $amount): ?string
    {
        try {
            $other_api = DB::table('other_api')->first();
            if (!$other_api || !$other_api->vtpass_username || !$other_api->vtpass_password) {
                Log::error('JAMB: VTpass credentials not configured');
                return 'fail';
            }

            $vtpass_token = base64_encode($other_api->vtpass_username . ":" . $other_api->vtpass_password);
            $system = DB::table('general')->first();

            $requestId = Carbon::now('Africa/Lagos')->format('YmdHi') . substr(md5($transid), 0, 8);

            $payload = [
                'serviceID' => 'jamb',
                'variation_code' => $request->variation_code,
                'billersCode' => $request->profile_id,
                'amount' => $amount,
                'phone' => $request->phone,
                'request_id' => $requestId,
            ];

            $endpoint = "https://vtpass.com/api/pay";
            $headers = [
                "Authorization: Basic " . $vtpass_token,
                'Content-Type: application/json',
            ];

            Log::info('JAMB VTpass SENDING:', ['url' => $endpoint, 'payload' => $payload]);
            $response = ApiSending::OTHERAPI($endpoint, $payload, $headers);
            Log::info('JAMB VTpass RECEIVED:', ['response' => $response]);

            // Save request_id for requery
            DB::table('jamb_purchases')->where('transid', $transid)->update([
                'request_id' => $requestId,
                'api_response' => json_encode($response),
            ]);

            if (!empty($response)) {
                $code = $response['code'] ?? '';
                $status = $response['content']['transactions']['status'] ?? '';
                $responseDesc = $response['response_description'] ?? '';

                // Check for success
                if ($code == '000' || $code == 'success' || $responseDesc == 'TRANSACTION SUCCESSFUL' || $status == 'delivered') {
                    // Save purchased code/PIN
                    $purchasedCode = $response['purchased_code'] ?? $response['Pin'] ?? null;
                    if ($purchasedCode) {
                        DB::table('jamb_purchases')->where('transid', $transid)->update([
                            'purchased_code' => $purchasedCode,
                        ]);
                    }
                    return 'success';
                }

                // Check for pending/processing
                if ($status == 'pending' || $code == '099') {
                    return 'process';
                }

                // Otherwise fail
                return 'fail';
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JAMB VTpass Error:', ['error' => $e->getMessage(), 'transid' => $transid]);
            return null;
        }
    }

    // ─── PRIVATE: REQUERY TRANSACTION ───

    public function requeryTransaction(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'transid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $purchase = DB::table('jamb_purchases')->where('transid', $request->transid)->first();
        if (!$purchase || !$purchase->request_id) {
            return response()->json(['status' => 'fail', 'message' => 'Transaction not found or no request ID'], 404);
        }

        $other_api = DB::table('other_api')->first();
        $vtpass_token = base64_encode($other_api->vtpass_username . ":" . $other_api->vtpass_password);

        $payload = ['request_id' => $purchase->request_id];
        $endpoint = "https://vtpass.com/api/requery";
        $headers = [
            "Authorization: Basic " . $vtpass_token,
            'Content-Type: application/json',
        ];

        $response = ApiSending::OTHERAPI($endpoint, $payload, $headers);
        Log::info('JAMB Requery:', ['request_id' => $purchase->request_id, 'response' => $response]);

        if (!empty($response)) {
            $status = $response['content']['transactions']['status'] ?? '';
            $code = $response['code'] ?? '';

            if ($code == '000' || $status == 'delivered') {
                $purchasedCode = $response['purchased_code'] ?? $response['Pin'] ?? null;
                if ($purchasedCode && $purchase->plan_status != 1) {
                    DB::table('jamb_purchases')->where('transid', $request->transid)->update([
                        'plan_status' => 1,
                        'purchased_code' => $purchasedCode,
                        'api_response' => json_encode($response),
                    ]);
                    DB::table('message')->where('transid', $request->transid)->update([
                        'plan_status' => 1,
                        'message' => '✅ JAMB ' . $purchase->variation_name . ' - PIN: ' . $purchasedCode,
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaction delivered',
                    'data' => [
                        'purchased_code' => $purchasedCode ?? $purchase->purchased_code,
                        'plan_status' => 1,
                    ],
                ]);
            } elseif ($status == 'pending') {
                return response()->json([
                    'status' => 'process',
                    'message' => 'Transaction still processing',
                ]);
            } else {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Transaction failed',
                    'data' => ['vtpass_status' => $status, 'code' => $code],
                ]);
            }
        }

        return response()->json(['status' => 'fail', 'message' => 'Unable to query transaction status'], 500);
    }

    // ─── HELPERS ───

    private function isJambEnabled(): bool
    {
        $settings = DB::table('settings')->first();
        return $settings && (int) ($settings->jamb_status ?? 0) === 1;
    }

    private function applyCharge(float $price, float $charge): float
    {
        return round($price + $charge, 2);
    }
}
