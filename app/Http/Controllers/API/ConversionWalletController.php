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
}