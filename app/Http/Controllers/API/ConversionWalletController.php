<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ConversionWallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ConversionWalletController extends Controller
{
    /**
     * Get user's conversion wallet balances
     */
    public function getWalletBalances(Request $request)
    {
        if (!empty($request->id)) {
            $check_user = DB::table('user')->where(['status' => 1, 'id' => $this->verifytoken($request->id)])->first();
            
            if ($check_user) {
                $user = User::find($check_user->id);
                
                return response()->json([
                    'status' => 'success',
                    'wallets' => [
                        'main_wallet' => number_format($user->bal, 2),
                        'a2cash_wallet' => number_format($user->getA2CashBalance(), 2),
                        'giftcard_wallet' => number_format($user->getGiftCardBalance(), 2),
                        'total_conversion' => number_format($user->getA2CashBalance() + $user->getGiftCardBalance(), 2)
                    ]
                ]);
            }
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ])->setStatusCode(403);
    }

    /**
     * Withdraw from conversion wallet to main wallet
     */
    public function withdrawFromConversionWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_type' => 'required|in:airtime_to_cash,gift_card',
            'amount' => 'required|numeric|min:100',
            'pin' => 'required|numeric|digits:4',
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ])->setStatusCode(400);
        }

        $verified_user_id = $this->verifyapptoken($request->user_id);
        $user = DB::table('user')->where(['id' => $verified_user_id, 'status' => 1])->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ])->setStatusCode(404);
        }

        // Verify PIN
        if (trim($user->pin) != trim($request->pin)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid transaction PIN'
            ])->setStatusCode(403);
        }

        try {
            return DB::transaction(function () use ($request, $user) {
                $userModel = User::find($user->id);
                
                // Get conversion wallet
                if ($request->wallet_type === 'airtime_to_cash') {
                    $conversionWallet = ConversionWallet::getOrCreateA2CashWallet($user->id);
                } else {
                    $conversionWallet = ConversionWallet::getOrCreateGiftCardWallet($user->id);
                }

                // Check sufficient balance
                if ($conversionWallet->balance < $request->amount) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient conversion wallet balance'
                    ])->setStatusCode(400);
                }

                // Debit conversion wallet
                $conversionWallet->debit(
                    $request->amount,
                    'Withdrawal to main wallet',
                    'withdrawal',
                    'WITHDRAW_' . time()
                );

                // Credit main wallet
                DB::table('user')->where('id', $user->id)->increment('bal', $request->amount);

                // Log transaction in message table
                $transid = 'CW_' . time() . rand(1000, 9999);
                DB::table('message')->insert([
                    'username' => $user->username,
                    'message' => 'Conversion Wallet Withdrawal - ' . ucfirst(str_replace('_', ' ', $request->wallet_type)),
                    'amount' => $request->amount,
                    'oldbal' => $user->bal,
                    'newbal' => $user->bal + $request->amount,
                    'habukhan_date' => Carbon::now(),
                    'transid' => $transid,
                    'plan_status' => 1,
                    'role' => 'credit'
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Withdrawal successful',
                    'transaction_id' => $transid,
                    'amount' => number_format($request->amount, 2),
                    'new_main_balance' => number_format($user->bal + $request->amount, 2),
                    'new_conversion_balance' => number_format($conversionWallet->fresh()->balance, 2)
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Withdrawal failed: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Get conversion wallet transaction history
     */
    public function getTransactionHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_type' => 'required|in:airtime_to_cash,gift_card',
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ])->setStatusCode(400);
        }

        $verified_user_id = $this->verifytoken($request->user_id);
        $user = DB::table('user')->where(['id' => $verified_user_id, 'status' => 1])->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ])->setStatusCode(404);
        }

        // Get conversion wallet
        $conversionWallet = ConversionWallet::where([
            'user_id' => $user->id,
            'wallet_type' => $request->wallet_type
        ])->first();

        if (!$conversionWallet) {
            return response()->json([
                'status' => 'success',
                'transactions' => [],
                'wallet_balance' => '0.00'
            ]);
        }

        $transactions = $conversionWallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($request->limit ?? 20);

        return response()->json([
            'status' => 'success',
            'transactions' => $transactions,
            'wallet_balance' => number_format($conversionWallet->balance, 2)
        ]);
    }

    /**
     * Direct bank transfer from conversion wallet (bypasses main wallet)
     */
    public function bankTransferFromConversionWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:100',
            'account_number' => 'required|string',
            'bank_code'      => 'required|string',
            'account_name'   => 'required|string',
            'pin'            => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $verified_user_id = $this->verifyapptoken($request->header('Authorization'));
        $user = DB::table('user')->where(['id' => $verified_user_id, 'status' => 1])->first();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        if (trim($user->pin) != trim($request->pin)) return response()->json(['status' => 'error', 'message' => 'Invalid transaction PIN'], 403);

        $amount = (float) $request->amount;

        return DB::transaction(function () use ($request, $user, $amount) {
            $a2cashWallet   = ConversionWallet::getOrCreateA2CashWallet($user->id);
            $giftCardWallet = ConversionWallet::getOrCreateGiftCardWallet($user->id);
            $totalConversion = $a2cashWallet->balance + $giftCardWallet->balance;

            if ($totalConversion < $amount) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Insufficient conversion wallet balance. Available: ₦' . number_format($totalConversion, 2),
                ], 400);
            }

            // Deduct from A2Cash first, then GiftCard
            $remaining = $amount;
            if ($a2cashWallet->balance > 0 && $remaining > 0) {
                $debit = min($remaining, $a2cashWallet->balance);
                $a2cashWallet->debit($debit, 'Bank withdrawal', 'bank_transfer', 'BT_' . time());
                $remaining -= $debit;
            }
            if ($remaining > 0 && $giftCardWallet->balance > 0) {
                $giftCardWallet->debit($remaining, 'Bank withdrawal', 'bank_transfer', 'BT_' . time() . '_gc');
            }

            $transid = 'CW_BT_' . time() . rand(1000, 9999);

            // Call Xixapay transfer directly (same as main transfer flow)
            $xixa = config('services.xixapay');
            $xixaResponse = \Illuminate\Support\Facades\Http::timeout(180)->withHeaders([
                'Authorization' => $xixa['authorization'],
                'api-key' => $xixa['api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://api.xixapay.com/api/v1/transfer', [
                'businessId' => $xixa['business_id'],
                'amount' => $amount,
                'bank' => $request->bank_code,
                'accountNumber' => $request->account_number,
                'narration' => 'VendLike Conversion Wallet Withdrawal - ' . $transid,
            ]);

            $xixaData = $xixaResponse->json();
            \Log::info('Conversion wallet bank transfer response', ['data' => $xixaData, 'transid' => $transid]);

            $transferSuccess = $xixaResponse->successful() && isset($xixaData['status']) && $xixaData['status'] === 'success';

            if (!$transferSuccess) {

                // Refund on failure
                $a2cashWallet->credit($amount, 'Refund - transfer failed', 'refund', $transid . '_refund');
                $errMsg = $xixaData['message'] ?? 'Transfer failed. Please try again.';
                if (str_contains(strtolower($errMsg), 'could not be processed')) {
                    $errMsg = 'This bank is not supported for withdrawals via our payment provider. Please use GTBank, Access Bank, UBA, Kolomoni, or other major banks.';
                }
                return response()->json(['status' => 'error', 'message' => $errMsg], 400);
            }

            DB::table('message')->insert([
                'username'      => $user->username,
                'message'       => 'Conversion Wallet Bank Transfer to ' . $request->account_name . ' (' . $request->account_number . ')',
                'amount'        => $amount,
                'oldbal'        => $totalConversion,
                'newbal'        => max(0, $totalConversion - $amount),
                'habukhan_date' => Carbon::now(),
                'transid'       => $transid,
                'plan_status'   => 1,
                'role'          => 'debit',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Transfer of ₦' . number_format($amount, 2) . ' initiated successfully.',
                'transid' => $transid,
                'amount'  => $amount,
            ]);
        });
    }
}
