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
use Carbon\Carbon;

class AdminGiftCardController extends Controller
{
    use GiftCardHelpers;
    /**
     * Get all gift card types for admin management
     */
    public function getGiftCardTypes(Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            if (!empty($request->id)) {
                $verified_id = $this->verifytoken($request->id);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        try {
            $giftCardTypes = GiftCardType::withCount(['redemptions', 'countries'])
                ->with('countries:id,name,code,flag_emoji')
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($card) {
                    // Add computed fields - use the model's accessor for consistency
                    $card->logo_url = $card->getLogoUrlAttribute();
                    $card->formatted_rate_change = $card->getFormattedRateChangeAttribute();
                    return $card;
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
     * Create new gift card type
     */
    public function createGiftCardType(Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            if (!empty($request->id)) {
                $verified_id = $this->verifytoken($request->id);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
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
            'name' => 'required|string|max:50|unique:gift_card_types,name',
            'rate' => 'required|numeric|min:1',
            'physical_rate' => 'nullable|numeric|min:1',
            'ecode_rate' => 'nullable|numeric|min:1',
            'min_amount' => 'required|numeric|min:1',
            'max_amount' => 'required|numeric|gt:min_amount',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive',
            'redemption_type' => 'required|in:both,physical,code',
            'require_code_for_physical' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'speed' => 'nullable|in:fast,slow',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'countries' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('giftcards/logos', 'public');
            }

            // Calculate rate change if previous_rate is provided
            $rateChange = 0;
            $rateTrend = 'stable';
            if ($request->previous_rate) {
                $rateChange = $request->rate - $request->previous_rate;
                $rateTrend = $rateChange > 0 ? 'up' : ($rateChange < 0 ? 'down' : 'stable');
            }

            // Convert require_code_for_physical to boolean
            $requireCodeForPhysical = filter_var($request->require_code_for_physical, FILTER_VALIDATE_BOOLEAN);

            $giftCardType = GiftCardType::create([
                'name' => $request->name,
                'rate' => $request->rate,
                'physical_rate' => $request->physical_rate,
                'ecode_rate' => $request->ecode_rate,
                'previous_rate' => $request->previous_rate,
                'rate_change' => $rateChange,
                'rate_trend' => $rateTrend,
                'min_amount' => $request->min_amount,
                'max_amount' => $request->max_amount,
                'description' => $request->description,
                'status' => $request->status,
                'redemption_type' => $request->redemption_type ?? 'both',
                'require_code_for_physical' => $requireCodeForPhysical,
                'sort_order' => $request->sort_order ?? 0,
                'speed' => $request->speed ?? 'fast',
                'logo_path' => $logoPath
            ]);

            // Attach countries if provided
            if ($request->countries) {
                $countries = json_decode($request->countries, true);
                
                if (is_array($countries) && !empty($countries)) {
                    foreach ($countries as $countryId) {
                        DB::table('gift_card_countries')->insert([
                            'gift_card_type_id' => $giftCardType->id,
                            'country_id' => $countryId,
                            'active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift card type created successfully',
                'data' => $giftCardType
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to create gift card type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update gift card type status only
     */
    public function updateGiftCardStatus($id, $token, Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $giftCardType = GiftCardType::find($id);
        if (!$giftCardType) {
            return response()->json(['status' => 'fail', 'message' => 'Gift card type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $giftCardType->update([
                'status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gift card status updated successfully',
                'data' => $giftCardType->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to update gift card status'
            ], 500);
        }
    }

    /**
     * Update gift card type
     */
    public function updateGiftCardType($id, $token, Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $giftCardType = GiftCardType::find($id);
        if (!$giftCardType) {
            return response()->json(['status' => 'fail', 'message' => 'Gift card type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:gift_card_types,name,' . $id,
            'rate' => 'required|numeric|min:1',
            'physical_rate' => 'nullable|numeric|min:1',
            'ecode_rate' => 'nullable|numeric|min:1',
            'min_amount' => 'required|numeric|min:1',
            'max_amount' => 'required|numeric|gt:min_amount',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive',
            'redemption_type' => 'required|in:both,physical,code',
            'require_code_for_physical' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'speed' => 'nullable|in:fast,slow',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'countries' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $logoPath = $giftCardType->logo_path;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('giftcards/logos', 'public');
            }

            // Calculate rate change if previous_rate is provided
            $rateChange = 0;
            $rateTrend = 'stable';
            if ($request->previous_rate) {
                $rateChange = $request->rate - $request->previous_rate;
                $rateTrend = $rateChange > 0 ? 'up' : ($rateChange < 0 ? 'down' : 'stable');
            }

            // Convert require_code_for_physical to boolean
            $requireCodeForPhysical = filter_var($request->require_code_for_physical, FILTER_VALIDATE_BOOLEAN);

            $giftCardType->update([
                'name' => $request->name,
                'rate' => $request->rate,
                'physical_rate' => $request->physical_rate,
                'ecode_rate' => $request->ecode_rate,
                'previous_rate' => $request->previous_rate,
                'rate_change' => $rateChange,
                'rate_trend' => $rateTrend,
                'min_amount' => $request->min_amount,
                'max_amount' => $request->max_amount,
                'description' => $request->description,
                'status' => $request->status,
                'redemption_type' => $request->redemption_type ?? 'both',
                'require_code_for_physical' => $requireCodeForPhysical,
                'sort_order' => $request->sort_order ?? 0,
                'speed' => $request->speed ?? $giftCardType->speed ?? 'fast',
                'logo_path' => $logoPath
            ]);

            // Update countries if provided
            if ($request->countries) {
                $countries = json_decode($request->countries, true);
                
                if (is_array($countries) && !empty($countries)) {
                    // Remove existing countries
                    DB::table('gift_card_countries')->where('gift_card_type_id', $giftCardType->id)->delete();
                    
                    // Add new countries
                    foreach ($countries as $countryId) {
                        DB::table('gift_card_countries')->insert([
                            'gift_card_type_id' => $giftCardType->id,
                            'country_id' => $countryId,
                            'active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            } else {
                // If no countries provided, remove all existing countries
                DB::table('gift_card_countries')->where('gift_card_type_id', $giftCardType->id)->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift card type updated successfully',
                'data' => $giftCardType->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to update gift card type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all redemption requests for admin processing
     */
    public function getRedemptionRequests(Request $request)
    {
        // Admin authentication check
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->token ?? $request->id;
            if (!empty($token)) {
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $this->verifytoken($token)])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        try {
            $status = $request->get('status', 'all');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 25);
            $search = $request->get('search', '');

            $query = GiftCardRedemption::with(['user:id,name,email,phone,username', 'giftCardType:id,name,rate'])
                ->orderBy('created_at', 'desc');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Search by reference, card code, or user name/email
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                      ->orWhere('card_code', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%")
                             ->orWhere('username', 'like', "%{$search}%");
                      });
                });
            }

            $redemptions = $query->paginate($perPage, ['*'], 'page', $page);

            // Add image URLs to each redemption (multi-file support)
            $items = collect($redemptions->items())->map(function ($item) {
                $item->image_url = $item->image_path ? asset('storage/' . $item->image_path) : null;
                $item->image_urls = $item->image_urls; // Uses model accessor - returns array of all file URLs
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
                'message' => 'Failed to fetch redemption requests'
            ], 500);
        }
    }

    /**
     * Approve gift card redemption
     */
    public function approveRedemption(Request $request, $id)
    {
        // Admin authentication check
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->token;
            if (!empty($token)) {
                $admin_user = DB::table('user')->where(['status' => 1, 'id' => $this->verifytoken($token)])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                })->first();
                
                if (!$admin_user) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $redemption = GiftCardRedemption::find($id);
        if (!$redemption) {
            return response()->json(['status' => 'fail', 'message' => 'Redemption not found'], 404);
        }

        if (!in_array($redemption->status, ['pending', 'processing'])) {
            return response()->json(['status' => 'fail', 'message' => 'Redemption already processed'], 400);
        }

        $validator = Validator::make($request->all(), [
            'final_amount' => 'nullable|numeric|min:1',
            'admin_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Admin can adjust final amount or use expected amount
            $finalAmount = $request->final_amount ?? $redemption->expected_naira;

            // Update redemption status
            $redemption->update([
                'status' => 'approved',
                'actual_naira' => $finalAmount,
                'admin_notes' => $request->admin_notes,
                'processed_by' => $admin_user->id,
                'processed_at' => now()
            ]);

            // AML COMPLIANCE: Credit Gift Card conversion wallet instead of main wallet
            $giftCardWallet = ConversionWallet::getOrCreateGiftCardWallet($redemption->user_id);
            $giftCardWallet->credit(
                $finalAmount,
                "Gift card redemption: {$redemption->giftCardType->name} - {$redemption->card_code}",
                'gift_card_sale',
                $redemption->reference
            );

            // Also log to message table so it appears in "All Transactions" for admin
            $user = DB::table('user')->where('id', $redemption->user_id)->first();
            if ($user) {
                // Update existing message record (created on submission) instead of inserting duplicate
                $updated = DB::table('message')
                    ->where('transid', $redemption->reference)
                    ->where('username', $user->username)
                    ->update([
                        'message' => 'You have successfully sold Gift Card',
                        'amount' => $finalAmount,
                        'oldbal' => $giftCardWallet->balance - $finalAmount,
                        'newbal' => $giftCardWallet->balance,
                        'habukhan_date' => Carbon::now(),
                        'plan_status' => 1,
                        'role' => 'credit'
                    ]);

                // Fallback: insert if no existing record found
                if (!$updated) {
                    DB::table('message')->insert([
                        'username' => $user->username,
                        'message' => 'You have successfully sold Gift Card',
                        'amount' => $finalAmount,
                        'oldbal' => $giftCardWallet->balance - $finalAmount,
                        'newbal' => $giftCardWallet->balance,
                        'habukhan_date' => Carbon::now(),
                        'transid' => $redemption->reference,
                        'plan_status' => 1,
                        'role' => 'credit'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Redemption approved successfully',
                'data' => [
                    'reference' => $redemption->reference,
                    'amount_credited' => $finalAmount,
                    'user_name' => $redemption->user->name
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Approve redemption failed', ['id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to approve redemption: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decline gift card redemption
     */
    public function declineRedemption(Request $request, $id)
    {
        // Admin authentication check
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->token;
            if (!empty($token)) {
                $admin_user = DB::table('user')->where(['status' => 1, 'id' => $this->verifytoken($token)])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                })->first();
                
                if (!$admin_user) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $redemption = GiftCardRedemption::find($id);
        if (!$redemption) {
            return response()->json(['status' => 'fail', 'message' => 'Redemption not found'], 404);
        }

        if (!in_array($redemption->status, ['pending', 'processing'])) {
            return response()->json(['status' => 'fail', 'message' => 'Redemption already processed'], 400);
        }

        $validator = Validator::make($request->all(), [
            'admin_notes' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $redemption->update([
                'status' => 'declined',
                'admin_notes' => $request->admin_notes,
                'processed_by' => $admin_user->id,
                'processed_at' => now()
            ]);

            // Update existing message record (created on submission) instead of inserting duplicate
            $user = DB::table('user')->where('id', $redemption->user_id)->first();
            if ($user) {
                $updated = DB::table('message')
                    ->where('transid', $redemption->reference)
                    ->where('username', $user->username)
                    ->update([
                        'message' => 'Gift Card sale was declined',
                        'amount' => $redemption->expected_naira,
                        'habukhan_date' => Carbon::now(),
                        'plan_status' => 2,
                        'role' => 'declined'
                    ]);

                // Fallback: insert if no existing record found
                if (!$updated) {
                    DB::table('message')->insert([
                        'username' => $user->username,
                        'message' => 'Gift Card sale was declined',
                        'amount' => $redemption->expected_naira,
                        'oldbal' => $user->bal,
                        'newbal' => $user->bal,
                        'habukhan_date' => Carbon::now(),
                        'transid' => $redemption->reference,
                        'plan_status' => 2,
                        'role' => 'declined'
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Redemption declined successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to decline redemption'
            ], 500);
        }
    }

    /**
     * Mark gift card redemption as processing
     */
    public function markProcessing(Request $request, $id)
    {
        // Admin authentication check
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->token;
            if (!empty($token)) {
                $admin_user = DB::table('user')->where(['status' => 1, 'id' => $this->verifytoken($token)])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                })->first();
                
                if (!$admin_user) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $redemption = GiftCardRedemption::find($id);
        if (!$redemption) {
            return response()->json(['status' => 'fail', 'message' => 'Redemption not found'], 404);
        }

        if ($redemption->status !== 'pending') {
            return response()->json(['status' => 'fail', 'message' => 'Only pending redemptions can be marked as processing'], 400);
        }

        try {
            $redemption->update([
                'status' => 'processing',
                'admin_notes' => $request->admin_notes,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Redemption marked as processing'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to update redemption status'
            ], 500);
        }
    }

    /**
     * Get gift card analytics for admin dashboard
     */
    public function getAnalytics(Request $request)
    {
        // Admin authentication check
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            $token = $request->token ?? $request->id;
            if (!empty($token)) {
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $this->verifytoken($token)])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();

            $analytics = [
                'total_redemptions' => GiftCardRedemption::count(),
                'pending_redemptions' => GiftCardRedemption::pending()->count(),
                'approved_redemptions' => GiftCardRedemption::approved()->count(),
                'declined_redemptions' => GiftCardRedemption::declined()->count(),
                'today_redemptions' => GiftCardRedemption::whereDate('created_at', $today)->count(),
                'month_redemptions' => GiftCardRedemption::where('created_at', '>=', $thisMonth)->count(),
                'total_value_approved' => GiftCardRedemption::approved()->sum('actual_naira'),
                'month_value_approved' => GiftCardRedemption::approved()
                    ->where('processed_at', '>=', $thisMonth)
                    ->sum('actual_naira'),
                'active_card_types' => GiftCardType::active()->count(),
                'total_conversion_wallets' => ConversionWallet::count(),
                'total_conversion_balance' => ConversionWallet::sum('balance')
            ];

            return response()->json([
                'status' => 'success',
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to fetch analytics'
            ], 500);
        }
    }

    /**
     * Bulk delete gift card types
     */
    public function bulkDeleteGiftCardTypes(Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            if (!empty($request->id)) {
                $verified_id = $this->verifytoken($request->id);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
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
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:gift_card_types,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $deletedCount = 0;
            foreach ($request->ids as $id) {
                $giftCardType = GiftCardType::find($id);
                if ($giftCardType) {
                    // Delete related countries
                    DB::table('gift_card_countries')->where('gift_card_type_id', $id)->delete();
                    
                    // Delete the gift card type
                    $giftCardType->delete();
                    $deletedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "{$deletedCount} gift card(s) deleted successfully",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to delete gift cards: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update gift card status
     */
    public function bulkUpdateGiftCardStatus(Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            if (!empty($request->id)) {
                $verified_id = $this->verifytoken($request->id);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
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
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:gift_card_types,id',
            'status' => 'required|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            $updatedCount = GiftCardType::whereIn('id', $request->ids)
                ->update(['status' => $request->status]);

            return response()->json([
                'status' => 'success',
                'message' => "{$updatedCount} gift card(s) {$request->status} successfully",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to update gift card status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all countries for admin
     */
    public function getCountries(Request $request)
    {
        // Admin authentication check using the same pattern as other admin endpoints
        $explode_url = explode(',', config('app.habukhan_app_key'));
        if (!$request->headers->get('origin') || in_array($request->headers->get('origin'), $explode_url)) {
            // Get the token from route parameter
            $token = $request->route('id') ?? $request->id;
            
            if (!empty($token)) {
                $verified_id = $this->verifytoken($token);
                
                if (!$verified_id) {
                    return response()->json(['status' => 'fail', 'message' => 'Invalid token'], 401);
                }
                
                $check_user = DB::table('user')->where(['status' => 1, 'id' => $verified_id])->where(function ($query) {
                    $query->where('type', 'ADMIN');
                });
                
                if ($check_user->count() == 0) {
                    return response()->json(['status' => 'fail', 'message' => 'Admin access required'], 403);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Token required'], 401);
            }
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        try {
            $countries = DB::table('countries')
                ->where('active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $countries
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Failed to fetch countries'
            ], 500);
        }
    }
}