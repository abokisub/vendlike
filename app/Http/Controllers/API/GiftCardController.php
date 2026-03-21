<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\GiftCardHelpers;
use App\Models\GiftCardType;
use App\Models\GiftCardRedemption;
use App\Models\ConversionWallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class GiftCardController extends Controller
{
    use GiftCardHelpers;

    /**
     * Get gift card lock status (buy + sell)
     */
    public function getLockStatus()
    {
        $settings = DB::table('settings')->first();
        return response()->json([
            'status' => 'success',
            'data' => [
                'buy_giftcard_lock' => (int) ($settings->buy_giftcard_lock ?? 0),
                'sell_giftcard_lock' => (int) ($settings->sell_giftcard_lock ?? 0),
            ],
        ]);
    }

    /**
     * Get available gift card types with rates
     */
    public function getGiftCardTypes(Request $request)
    {
        try {
            $giftCardTypes = GiftCardType::active()
                ->ordered()
                ->get()
                ->map(function ($card) {
                    // Get supported countries
                    $countries = $card->countries()
                        ->where('gift_card_countries.active', true)
                        ->get()
                        ->map(function ($country) {
                            return [
                                'id' => $country->id,
                                'name' => $country->name,
                                'code' => $country->code,
                                'flag_emoji' => $country->flag_emoji,
                            ];
                        });

                    return [
                        'id' => $card->id,
                        'name' => $card->name,
                        'rate' => $card->rate,
                        'physical_rate' => $card->physical_rate,
                        'ecode_rate' => $card->ecode_rate,
                        'previous_rate' => $card->previous_rate,
                        'rate_change' => $card->rate_change,
                        'rate_trend' => $card->rate_trend ?? 'stable',
                        'formatted_rate_change' => $card->formatted_rate_change,
                        'min_amount' => $card->min_amount,
                        'max_amount' => $card->max_amount,
                        'icon' => $card->icon,
                        'description' => $card->description,
                        'redemption_type' => $card->redemption_type,
                        'logo_url' => $card->logo_url,
                        'sort_order' => $card->sort_order,
                        'speed' => $card->speed ?? 'fast',
                        'countries' => $countries,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $giftCardTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to fetch gift card types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit gift card redemption request
     */
    public function submitRedemption(Request $request)
    {
        // Check if sell gift card is locked
        $settings = DB::table('settings')->first();
        if (($settings->sell_giftcard_lock ?? 0) == 1) {
            return response()->json(['status' => 'fail', 'message' => 'Sell gift card is currently unavailable'], 503);
        }

        $explode_url = explode(',', config('app.habukhan_app_key'));
        $authHeader = $request->header('Authorization');
        $deviceKey = config('app.habukhan_device_key');

        // Authentication logic (similar to existing controllers)
        if ($deviceKey == $authHeader || $request->header('X-Device-Key') == $deviceKey || str_starts_with($authHeader ?? '', 'Bearer ')) {
            // APP AUTH
            \Log::info('Gift card submit: APP AUTH path', [
                'user_id_param' => $request->user_id,
                'has_device_key' => !empty($request->header('X-Device-Key')),
            ]);
            $verified_id = $this->verifyapptoken($request->user_id ?? $request->route('id'));
            $user = DB::table('user')->where(['id' => $verified_id, 'status' => 1])->first();
            
            if (!$user) {
                return response()->json(['status' => 'fail', 'message' => 'User not found or blocked'], 403);
            }
        } else if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            // WEB AUTH
            $verified_id = $this->verifytoken($request->token);
            $user = DB::table('user')->where(['id' => $verified_id, 'status' => 1])->first();
            
            if (!$user) {
                return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 403);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        // Get gift card type first to check redemption_type
        $cardType = GiftCardType::find($request->gift_card_type_id);
        if (!$cardType) {
            return response()->json(['status' => 'fail', 'message' => 'Gift card type not found'], 400);
        }

        // User sends which method they chose: 'physical' or 'code'
        // If card is 'both', user picks one. If card is 'physical' or 'code', that's the only option.
        $userMethod = $request->redemption_method;
        
        // Validate the user's chosen method is allowed for this card type
        $allowedMethods = [];
        if ($cardType->redemption_type === 'both') {
            $allowedMethods = ['physical', 'code'];
        } else {
            $allowedMethods = [$cardType->redemption_type];
        }
        
        if (!$userMethod || !in_array($userMethod, $allowedMethods)) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Invalid redemption method for this card type'
            ], 400);
        }

        // Build validation rules based on user's chosen method
        $rules = [
            'gift_card_type_id' => 'required|exists:gift_card_types,id',
            'card_amount' => 'required|numeric|min:1|max:10000',
            'redemption_method' => 'required|in:physical,code',
        ];

        // Code required only when user chose 'code'
        if ($userMethod === 'code') {
            $rules['card_code'] = 'required|string|max:255';
        } else {
            $rules['card_code'] = 'nullable|string|max:255';
        }

        // Multi-file upload: card_images[] array (1-5 files), supports images + PDFs
        if ($userMethod === 'physical') {
            $rules['card_images'] = 'required|array|min:1|max:5';
            $rules['card_images.*'] = 'required|file|mimes:jpeg,png,jpg,pdf|max:5120'; // 5MB per file
        } else {
            $rules['card_images'] = 'nullable|array|max:5';
            $rules['card_images.*'] = 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120';
        }

        // Backward compat: also accept single card_image
        if (!$request->hasFile('card_images') && $request->hasFile('card_image')) {
            $rules['card_image'] = ($userMethod === 'physical' ? 'required' : 'nullable') . '|file|mimes:jpeg,png,jpg,pdf|max:5120';
            unset($rules['card_images'], $rules['card_images.*']);
        }

        // Validate input
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            \Log::warning('Gift card validation failed', [
                'errors' => $validator->errors()->toArray(),
                'user_id' => $user->id ?? null,
                'input_keys' => array_keys($request->all()),
                'redemption_method' => $userMethod,
            ]);
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            // Check if card already submitted (prevent duplicates)
            // Only check code duplicates if a code was provided
            if ($request->card_code) {
                $existing = GiftCardRedemption::where('card_code', $request->card_code)
                    ->where('gift_card_type_id', $request->gift_card_type_id)
                    ->whereIn('status', ['pending', 'approved', 'processing'])
                    ->first();

                if ($existing) {
                    return response()->json([
                        'status' => 'fail',
                        'message' => 'This gift card has already been submitted'
                    ], 400);
                }
            }

            if (!$cardType->isAmountValid($request->card_amount)) {
                return response()->json([
                    'status' => 'fail',
                    'message' => "Amount must be between \${$cardType->min_amount} and \${$cardType->max_amount}"
                ], 400);
            }

            // Check user daily limits (max 5 submissions per day)
            $dailyCount = GiftCardRedemption::where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->count();

            if ($dailyCount >= 5) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Daily submission limit reached (5 per day)'
                ], 400);
            }

            // Calculate expected conversion using method-specific rate
            $expectedNaira = $cardType->calculateConversion($request->card_amount, $userMethod);

            // Store uploaded files (1-5 images/PDFs)
            $imagePaths = [];
            $storagePath = 'giftcards/' . date('Y/m');

            if ($request->hasFile('card_images')) {
                foreach ($request->file('card_images') as $file) {
                    $imagePaths[] = $file->store($storagePath, 'public');
                }
            } elseif ($request->hasFile('card_image')) {
                // Backward compat: single file upload
                $imagePaths[] = $request->file('card_image')->store($storagePath, 'public');
            }

            // Generate unique reference
            $reference = GiftCardRedemption::generateReference();

            // Create redemption request
            $redemption = GiftCardRedemption::create([
                'user_id' => $user->id,
                'gift_card_type_id' => $request->gift_card_type_id,
                'card_code' => $request->card_code,
                'card_amount' => $request->card_amount,
                'expected_naira' => $expectedNaira,
                'image_path' => $imagePaths[0] ?? null,
                'additional_images' => !empty($imagePaths) ? json_encode($imagePaths) : null,
                'reference' => $reference,
                'status' => 'pending',
                'redemption_method' => $userMethod,
            ]);

            // Insert into message table so it appears in main transaction history immediately
            DB::table('message')->insert([
                'username' => $user->username,
                'message' => 'You have submitted Gift Card for review',
                'amount' => $expectedNaira,
                'oldbal' => $user->bal ?? 0,
                'newbal' => $user->bal ?? 0,
                'habukhan_date' => \Carbon\Carbon::now(),
                'transid' => $reference,
                'plan_status' => 0,
                'role' => 'credit'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gift card submitted successfully',
                'data' => [
                    'reference' => $reference,
                    'expected_amount' => $expectedNaira,
                    'card_type' => $cardType->name,
                    'status' => 'pending'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to submit gift card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's gift card redemption history
     */
    public function getRedemptionHistory(Request $request)
    {
        $explode_url = explode(',', config('app.habukhan_app_key'));
        $authHeader = $request->header('Authorization');
        $deviceKey = config('app.habukhan_device_key');

        // Authentication logic
        if ($deviceKey == $authHeader || $request->header('X-Device-Key') == $deviceKey || str_starts_with($authHeader ?? '', 'Bearer ')) {
            $verified_id = $this->verifyapptoken($request->user_id ?? $request->route('id'));
        } else if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $verified_id = $this->verifytoken($request->token);
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);

            $redemptions = GiftCardRedemption::where('user_id', $verified_id)
                ->with('giftCardType:id,name,icon,logo_path')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Add image URLs (multi-file support)
            $items = collect($redemptions->items())->map(function ($item) {
                $item->image_url = $item->image_path ? asset('storage/' . $item->image_path) : null;
                $item->image_urls = $item->image_urls; // Uses model accessor
                if ($item->giftCardType && $item->giftCardType->logo_path) {
                    $item->giftCardType->logo_url = asset('storage/' . $item->giftCardType->logo_path);
                }
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'current_page' => $redemptions->currentPage(),
                    'total_pages' => $redemptions->lastPage(),
                    'per_page' => $redemptions->perPage(),
                    'total' => $redemptions->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to fetch redemption history'
            ], 500);
        }
    }

    /**
     * Get conversion wallet balance and info
     */
    public function getConversionWallet(Request $request)
    {
        $explode_url = explode(',', config('app.habukhan_app_key'));
        $authHeader = $request->header('Authorization');
        $deviceKey = config('app.habukhan_device_key');

        // Authentication logic
        if ($deviceKey == $authHeader || $request->header('X-Device-Key') == $deviceKey || str_starts_with($authHeader ?? '', 'Bearer ')) {
            $verified_id = $this->verifyapptoken($request->user_id ?? $request->route('id'));
        } else if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $verified_id = $this->verifytoken($request->token);
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        try {
            $wallet = ConversionWallet::getOrCreateForUser($verified_id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'balance' => $wallet->balance,
                    'total_earned' => $wallet->total_earned,
                    'total_withdrawn' => $wallet->total_withdrawn,
                    'created_at' => $wallet->created_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to fetch conversion wallet'
            ], 500);
        }
    }

    /**
     * Withdraw from conversion wallet to bank account
     */
    public function withdrawConversionBalance(Request $request)
    {
        // TODO: Implement withdrawal using existing TransferRouter
        // This will integrate with your existing bank transfer system
        
        return response()->json([
            'status' => 'info',
            'message' => 'Withdrawal feature coming soon'
        ]);
    }
}