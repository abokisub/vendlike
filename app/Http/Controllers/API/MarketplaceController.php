<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceVendor;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Services\FezDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MarketplaceController extends Controller
{
    // ─── ADMIN: CATEGORIES ───

    public function adminGetCategories(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $categories = MarketplaceCategory::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    public function adminCreateCategory(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $slug = Str::slug($request->name);
        if (MarketplaceCategory::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('marketplace/categories', 'public');
        }

        $category = MarketplaceCategory::create([
            'name' => $request->name,
            'slug' => $slug,
            'icon' => $request->icon,
            'image' => $imagePath,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => true,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Category created', 'data' => $category]);
    }

    public function adminUpdateCategory(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $catId = $request->route('catId') ?? $id;
        $category = MarketplaceCategory::find($catId);
        if (!$category) {
            return response()->json(['status' => 'fail', 'message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        if ($request->has('name'))
            $category->name = $request->name;
        if ($request->has('icon'))
            $category->icon = $request->icon;
        if ($request->has('description'))
            $category->description = $request->description;
        if ($request->has('sort_order'))
            $category->sort_order = $request->sort_order;
        if ($request->has('is_active'))
            $category->is_active = (bool) $request->is_active;

        if ($request->hasFile('image')) {
            $category->image = $request->file('image')->store('marketplace/categories', 'public');
        }

        $category->save();

        return response()->json(['status' => 'success', 'message' => 'Category updated', 'data' => $category]);
    }

    public function adminDeleteCategory(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $catId = $request->route('catId') ?? $id;
        $category = MarketplaceCategory::find($catId);
        if (!$category) {
            return response()->json(['status' => 'fail', 'message' => 'Category not found'], 404);
        }

        if ($category->products()->count() > 0) {
            return response()->json(['status' => 'fail', 'message' => 'Cannot delete category with products. Remove products first.'], 400);
        }

        $category->delete();
        return response()->json(['status' => 'success', 'message' => 'Category deleted']);
    }

    // ─── ADMIN: PRODUCTS ───

    public function adminGetProducts(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $query = MarketplaceProduct::with(['category', 'vendor']);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('sort_order')->orderByDesc('created_at')->get();

        // Append image URLs
        $products->each(function ($p) {
            $p->image_urls = $p->image_urls;
        });

        return response()->json(['status' => 'success', 'data' => $products]);
    }

    public function adminCreateProduct(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:marketplace_categories,id',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:1',
            'discount_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0.1',
            'sizes' => 'nullable|string', // comma-separated
            'colors' => 'nullable|string', // comma-separated
            'images.*' => 'image|max:3072',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $slug = Str::slug($request->name);
        if (MarketplaceProduct::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $imagePaths[] = $file->store('marketplace/products', 'public');
            }
        }

        $sizes = $request->sizes ? array_map('trim', explode(',', $request->sizes)) : null;
        $colors = $request->colors ? array_map('trim', explode(',', $request->colors)) : null;

        $product = MarketplaceProduct::create([
            'category_id' => $request->category_id,
            'vendor_id' => $request->vendor_id ?: null,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'discount_price' => $request->discount_price,
            'stock' => $request->stock,
            'weight' => $request->weight ?? 1.0,
            'images' => $imagePaths,
            'sizes' => $sizes,
            'colors' => $colors,
            'is_active' => true,
            'is_featured' => (bool) $request->is_featured,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        $product->load(['category', 'vendor']);
        $product->image_urls = $product->image_urls;

        return response()->json(['status' => 'success', 'message' => 'Product created', 'data' => $product]);
    }

    public function adminUpdateProduct(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $prodId = $request->route('prodId') ?? $id;
        $product = MarketplaceProduct::find($prodId);
        if (!$product) {
            return response()->json(['status' => 'fail', 'message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'category_id' => 'sometimes|exists:marketplace_categories,id',
            'price' => 'sometimes|numeric|min:1',
            'discount_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'sizes' => 'nullable|string',
            'colors' => 'nullable|string',
            'images.*' => 'image|max:3072',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        if ($request->has('name'))
            $product->name = $request->name;
        if ($request->has('category_id'))
            $product->category_id = $request->category_id;
        if ($request->has('vendor_id'))
            $product->vendor_id = $request->vendor_id ?: null;
        if ($request->has('description'))
            $product->description = $request->description;
        if ($request->has('price'))
            $product->price = $request->price;
        if ($request->has('discount_price'))
            $product->discount_price = $request->discount_price;
        if ($request->has('stock'))
            $product->stock = $request->stock;
        if ($request->has('weight'))
            $product->weight = $request->weight;
        if ($request->has('is_active'))
            $product->is_active = (bool) $request->is_active;
        if ($request->has('is_featured'))
            $product->is_featured = (bool) $request->is_featured;
        if ($request->has('sort_order'))
            $product->sort_order = $request->sort_order;

        if ($request->has('sizes')) {
            $product->sizes = $request->sizes ? array_map('trim', explode(',', $request->sizes)) : null;
        }
        if ($request->has('colors')) {
            $product->colors = $request->colors ? array_map('trim', explode(',', $request->colors)) : null;
        }

        // Append new images to existing
        if ($request->hasFile('images')) {
            $existing = $product->images ?? [];
            foreach ($request->file('images') as $file) {
                $existing[] = $file->store('marketplace/products', 'public');
            }
            $product->images = $existing;
        }

        // Remove specific images
        if ($request->has('remove_images')) {
            $toRemove = is_array($request->remove_images) ? $request->remove_images : explode(',', $request->remove_images);
            $product->images = array_values(array_filter($product->images ?? [], function ($img) use ($toRemove) {
                return !in_array($img, $toRemove);
            }));
        }

        $product->save();
        $product->load(['category', 'vendor']);
        $product->image_urls = $product->image_urls;

        return response()->json(['status' => 'success', 'message' => 'Product updated', 'data' => $product]);
    }

    public function adminDeleteProduct(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $prodId = $request->route('prodId') ?? $id;
        $product = MarketplaceProduct::find($prodId);
        if (!$product) {
            return response()->json(['status' => 'fail', 'message' => 'Product not found'], 404);
        }

        $product->delete();
        return response()->json(['status' => 'success', 'message' => 'Product deleted']);
    }

    // ─── ADMIN: ORDERS ───

    public function adminGetOrders(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $query = MarketplaceOrder::with('items');

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', '%' . $request->search . '%')
                    ->orWhere('delivery_name', 'like', '%' . $request->search . '%')
                    ->orWhere('delivery_phone', 'like', '%' . $request->search . '%');
            });
        }

        $orders = $query->orderByDesc('created_at')->get();

        // Attach username
        foreach ($orders as $order) {
            $user = DB::table('user')->where('id', $order->user_id)->first(['username', 'name', 'email']);
            $order->user = $user;
        }

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    public function adminUpdateOrder(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $orderId = $request->route('orderId') ?? $id;
        $order = MarketplaceOrder::with('items')->find($orderId);
        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        if ($request->has('status')) {
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($request->status, $validStatuses)) {
                return response()->json(['status' => 'fail', 'message' => 'Invalid status'], 400);
            }

            // If cancelling, mark for manual refund (payment was via Monnify, not wallet)
            if ($request->status === 'cancelled' && $order->status !== 'cancelled') {
                $user = DB::table('user')->where('id', $order->user_id)->first();
                DB::table('message')->where('transid', $order->reference)->update([
                    'message' => 'Marketplace order cancelled - Refund pending (Monnify)',
                    'plan_status' => 2,
                    'role' => 'refund',
                    'newbal' => $user->bal ?? 0,
                ]);
            }

            // If delivered, mark transaction as success
            if ($request->status === 'delivered' && $order->status !== 'delivered') {
                DB::table('message')->where('transid', $order->reference)->update([
                    'plan_status' => 1,
                    'message' => 'Marketplace order delivered',
                ]);
            }

            $order->status = $request->status;
        }

        if ($request->has('tracking_number'))
            $order->tracking_number = $request->tracking_number;
        if ($request->has('admin_note'))
            $order->admin_note = $request->admin_note;

        $order->save();

        // Send email notification for shipped/delivered status changes
        try {
            $user = DB::table('user')->where('id', $order->user_id)->first();
            if ($user && !empty($user->email) && in_array($request->status, ['shipped', 'delivered'])) {
                $items = MarketplaceOrderItem::where('order_id', $order->id)->get();
                $emailItems = [];
                foreach ($items as $item) {
                    $emailItems[] = [
                        'name' => $item->product_name,
                        'quantity' => $item->quantity,
                    ];
                }

                if ($request->status === 'shipped') {
                    $emailData = [
                        'email' => $user->email,
                        'username' => $user->username,
                        'title' => '🚚 Order Shipped - ' . $order->reference . ' | ' . config('app.name'),
                        'reference' => $order->reference,
                        'grand_total' => $order->grand_total,
                        'tracking_number' => $order->tracking_number,
                        'items' => $emailItems,
                        'delivery_name' => $order->delivery_name,
                        'delivery_phone' => $order->delivery_phone,
                        'delivery_address' => $order->delivery_address,
                        'delivery_state' => $order->delivery_state,
                    ];
                    $pdfData = array_merge($emailData, [
                        'invoice_type' => 'SHIPPING NOTICE',
                        'status' => 'SHIPPED',
                        'customer_name' => $user->name ?? $user->username,
                        'customer_email' => $user->email,
                        'total_amount' => $order->total_amount,
                        'delivery_fee' => $order->delivery_fee,
                        'date' => now()->format('d M Y, h:i A'),
                    ]);
                    $attachment = \App\Services\InvoiceService::generatePdf('MARKETPLACE', $pdfData);
                    \App\Http\Controllers\MailController::send_mail($emailData, 'email.order_shipped', $attachment);
                } elseif ($request->status === 'delivered') {
                    $emailData = [
                        'email' => $user->email,
                        'username' => $user->username,
                        'title' => '✅ Order Delivered - ' . $order->reference . ' | ' . config('app.name'),
                        'reference' => $order->reference,
                        'grand_total' => $order->grand_total,
                        'items' => $emailItems,
                        'date' => now()->format('d M Y, h:i A'),
                    ];
                    \App\Http\Controllers\MailController::send_mail($emailData, 'email.order_delivered');
                }
            }
        } catch (\Exception $e) {
            Log::error('Marketplace status email failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success', 'message' => 'Order updated', 'data' => $order]);
    }

    /**
     * Admin: Verify Monnify payment for a pending order
     */
    public function adminVerifyPayment(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $orderId = $request->route('orderId') ?? $id;
        $order = MarketplaceOrder::with('items')->find($orderId);
        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 'success', 'message' => 'Payment already confirmed', 'data' => ['payment_status' => 'paid', 'order_status' => $order->status]]);
        }

        $habukhan_key = DB::table('habukhan_key')->first();
        $monnifyRef = $order->monnify_reference ?? $order->reference;

        Log::info('Admin verify payment', ['order_id' => $order->id, 'reference' => $order->reference, 'monnify_ref' => $monnifyRef]);

        $verified = $this->verifyMonnifyPayment($monnifyRef, $habukhan_key);

        Log::info('Monnify verify result', ['result' => $verified]);

        if ($verified && $verified['paymentStatus'] === 'PAID') {
            $this->completeOrder($order);
            $order->refresh();
            return response()->json([
                'status' => 'success',
                'message' => 'Payment confirmed via Monnify. Order is now processing.',
                'data' => [
                    'payment_status' => 'paid',
                    'order_status' => $order->status,
                    'reference' => $order->reference,
                ],
            ]);
        } else {
            $monnifyStatus = $verified['paymentStatus'] ?? 'UNKNOWN';
            return response()->json([
                'status' => 'fail',
                'message' => "Payment not confirmed. Monnify status: {$monnifyStatus}. Customer may not have completed payment.",
                'data' => ['monnify_status' => $monnifyStatus],
            ], 400);
        }
    }

    // ─── ADMIN: VENDORS ───

    public function adminGetVendors(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $vendors = MarketplaceVendor::withCount('products')->orderBy('name')->get();
        return response()->json(['status' => 'success', 'data' => $vendors]);
    }

    public function adminCreateVendor(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'business_name' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'description' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }
        $vendor = MarketplaceVendor::create([
            'name' => $request->name,
            'business_name' => $request->business_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'description' => $request->description,
            'is_active' => true,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Vendor created', 'data' => $vendor]);
    }

    public function adminUpdateVendor(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $vendorId = $request->route('vendorId') ?? $id;
        $vendor = MarketplaceVendor::find($vendorId);
        if (!$vendor) {
            return response()->json(['status' => 'fail', 'message' => 'Vendor not found'], 404);
        }
        if ($request->has('name'))
            $vendor->name = $request->name;
        if ($request->has('business_name'))
            $vendor->business_name = $request->business_name;
        if ($request->has('phone'))
            $vendor->phone = $request->phone;
        if ($request->has('email'))
            $vendor->email = $request->email;
        if ($request->has('description'))
            $vendor->description = $request->description;
        if ($request->has('is_active'))
            $vendor->is_active = (bool) $request->is_active;
        $vendor->save();
        return response()->json(['status' => 'success', 'message' => 'Vendor updated', 'data' => $vendor]);
    }

    public function adminDeleteVendor(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        $vendorId = $request->route('vendorId') ?? $id;
        $vendor = MarketplaceVendor::find($vendorId);
        if (!$vendor) {
            return response()->json(['status' => 'fail', 'message' => 'Vendor not found'], 404);
        }
        // Unlink products (vendor_id set null via FK onDelete set null)
        $vendor->delete();
        return response()->json(['status' => 'success', 'message' => 'Vendor deleted']);
    }

    // ─── ADMIN: SETTINGS ───

    public function adminGetSettings(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $settings = DB::table('settings')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'marketplace_status' => (int) ($settings->marketplace_status ?? 1),
                'marketplace_delivery_fee' => (float) ($settings->marketplace_delivery_fee ?? 0),
                'marketplace_delivery_mode' => $settings->marketplace_delivery_mode ?? 'self',
                'marketplace_payment_provider' => $settings->marketplace_payment_provider ?? 'xixapay',
            ],
        ]);
    }

    public function adminUpdateSettings(Request $request)
    {
        $token = $request->token ?? $request->route('id');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $updates = [];
        if ($request->has('marketplace_status'))
            $updates['marketplace_status'] = (int) $request->marketplace_status;
        if ($request->has('marketplace_delivery_fee'))
            $updates['marketplace_delivery_fee'] = (float) $request->marketplace_delivery_fee;
        if ($request->has('marketplace_delivery_mode'))
            $updates['marketplace_delivery_mode'] = $request->marketplace_delivery_mode;
        if ($request->has('marketplace_payment_provider') && in_array($request->marketplace_payment_provider, ['xixapay', 'monnify', 'pointwave'])) {
            $updates['marketplace_payment_provider'] = $request->marketplace_payment_provider;
        }

        if (!empty($updates)) {
            DB::table('settings')->update($updates);
        }

        return response()->json(['status' => 'success', 'message' => 'Settings updated']);
    }

    // ─── MOBILE: BROWSE ───

    public function getCategories(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $categories = MarketplaceCategory::where('is_active', true)
            ->withCount(['products' => function ($q) {
                $q->where('is_active', true); }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'icon' => $c->icon,
                    'image' => $c->image ? url('storage/' . $c->image) : null,
                    'products_count' => $c->products_count,
                ];
            });

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    public function getProducts(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        if (!$this->isMarketplaceEnabled()) {
            return response()->json(['status' => 'fail', 'message' => 'Marketplace is currently unavailable'], 503);
        }

        $query = MarketplaceProduct::with('category')
            ->where('is_active', true);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->featured) {
            $query->where('is_featured', true);
        }
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('sort_order')->orderByDesc('created_at')->get();

        $data = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category ? $p->category->name : null,
                'category_id' => $p->category_id,
                'price' => $p->price,
                'discount_price' => $p->discount_price,
                'effective_price' => $p->effective_price,
                'stock' => $p->stock,
                'out_of_stock' => $p->stock <= 0,
                'weight' => (float) $p->weight,
                'images' => $p->image_urls,
                'sizes' => $p->sizes,
                'colors' => $p->colors,
                'is_featured' => $p->is_featured,
                'description' => $p->description,
            ];
        });

        $settings = DB::table('settings')->first();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'delivery_fee' => (float) ($settings->marketplace_delivery_fee ?? 0),
            'delivery_mode' => $settings->marketplace_delivery_mode ?? 'self',
        ]);
    }

    public function getProduct(Request $request, $id)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $product = MarketplaceProduct::with('category')->find($id);
        if (!$product || !$product->is_active) {
            return response()->json(['status' => 'fail', 'message' => 'Product not found'], 404);
        }

        $settings = DB::table('settings')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category ? $product->category->name : null,
                'category_id' => $product->category_id,
                'price' => $product->price,
                'discount_price' => $product->discount_price,
                'effective_price' => $product->effective_price,
                'stock' => $product->stock,
                'weight' => (float) $product->weight,
                'images' => $product->image_urls,
                'sizes' => $product->sizes,
                'colors' => $product->colors,
                'is_featured' => $product->is_featured,
                'description' => $product->description,
            ],
            'delivery_fee' => (float) ($settings->marketplace_delivery_fee ?? 0),
            'delivery_mode' => $settings->marketplace_delivery_mode ?? 'self',
        ]);
    }

    // ─── MOBILE: PLACE ORDER ───

    public function placeOrder(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        if (!$this->isMarketplaceEnabled()) {
            return response()->json(['status' => 'fail', 'message' => 'Marketplace is currently unavailable'], 503);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:marketplace_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.size' => 'nullable|string',
            'items.*.color' => 'nullable|string',
            'delivery_name' => 'required|string|max:100',
            'delivery_phone' => 'required|string|max:20',
            'delivery_address' => 'required|string|max:500',
            'delivery_city' => 'nullable|string|max:100',
            'delivery_state' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        $user = DB::table('user')->where('id', $user_id)->first();
        if (!$user) {
            return response()->json(['status' => 'fail', 'message' => 'User not found'], 404);
        }

        // Validate items, calculate total + total weight
        $totalAmount = 0;
        $totalWeight = 0;
        $orderItems = [];

        foreach ($request->items as $item) {
            $product = MarketplaceProduct::find($item['product_id']);
            if (!$product || !$product->is_active) {
                return response()->json(['status' => 'fail', 'message' => 'Product "' . ($product->name ?? 'Unknown') . '" is unavailable'], 400);
            }
            if ($product->stock < $item['quantity']) {
                return response()->json(['status' => 'fail', 'message' => '"' . $product->name . '" has only ' . $product->stock . ' in stock'], 400);
            }

            $unitPrice = $product->effective_price;
            $subtotal = $unitPrice * $item['quantity'];
            $totalAmount += $subtotal;
            $totalWeight += ($product->weight ?? 1) * $item['quantity'];

            $orderItems[] = [
                'product' => $product,
                'product_name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $item['quantity'],
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
                'subtotal' => $subtotal,
            ];
        }

        // Get delivery cost based on admin delivery mode setting
        $deliveryFee = 0;
        $deliveryEta = null;
        $settings = DB::table('settings')->first();
        $deliveryMode = $settings->marketplace_delivery_mode ?? 'self';

        if ($deliveryMode === 'self') {
            // Use admin's flat delivery fee
            $deliveryFee = (float) ($settings->marketplace_delivery_fee ?? 0);
        } else {
            // Use Fez API
            try {
                $fez = new FezDeliveryService();
                $costResult = $fez->getDeliveryCost($request->delivery_state, $totalWeight);
                if (isset($costResult['totalCost'])) {
                    $deliveryFee = (float) $costResult['totalCost'];
                }
                $etaResult = $fez->getDeliveryEstimate('Lagos', $request->delivery_state);
                if (isset($etaResult['data']['eta'])) {
                    $deliveryEta = $etaResult['data']['eta'];
                }
            } catch (\Exception $e) {
                Log::error('Fez delivery cost failed', ['error' => $e->getMessage()]);
                // Fall back to flat fee
                $deliveryFee = (float) ($settings->marketplace_delivery_fee ?? 0);
            }
        }

        $grandTotal = $totalAmount + $deliveryFee;
        $reference = $this->purchase_ref('MP_');

        try {
            DB::beginTransaction();

            // Create order with payment_status = pending (no wallet debit)
            $paymentProvider = DB::table('settings')->value('marketplace_payment_provider') ?? 'xixapay';
            $order = MarketplaceOrder::create([
                'user_id' => $user_id,
                'reference' => $reference,
                'total_amount' => $totalAmount,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $grandTotal,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $paymentProvider,
                'delivery_name' => $request->delivery_name,
                'delivery_phone' => $request->delivery_phone,
                'delivery_address' => $request->delivery_address,
                'delivery_city' => $request->delivery_city,
                'delivery_state' => $request->delivery_state,
                'delivery_eta' => $deliveryEta,
            ]);

            foreach ($orderItems as $item) {
                MarketplaceOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product_name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Marketplace order creation failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'fail', 'message' => 'Order failed. Please try again.'], 500);
        }

        // ── PAYMENT PROVIDER SWITCH ──────────────────────────────────
        $paymentProvider = DB::table('settings')->value('marketplace_payment_provider') ?? 'xixapay';

        if ($paymentProvider === 'xixapay') {
            // Xixapay Dynamic Account — try all supported bank codes
            $xixa = config('services.xixapay');

            // Try all 3 bank codes: Palmpay, Kolomoni, Safehaven
            $bankCodesToTry = [['20867'], ['20987'], ['29007']];
            $xixaData = null;
            $xixaResponse = null;

            // Reuse existing customer_id if we have one (avoids duplicate KYC issues)
            $existingCustomerId = $user->xixapay_customer_id ?? null;

            foreach ($bankCodesToTry as $bankCode) {
                if ($existingCustomerId) {
                    // Option 1: existing KYC customer
                    $xixaPayload = [
                        'customer_id' => $existingCustomerId,
                        'bankCode' => $bankCode,
                        'businessId' => $xixa['business_id'],
                        'accountType' => 'dynamic',
                        'amount' => $grandTotal,
                        'externalReference' => $reference,
                        'callbackUrl' => url('') . '/api/marketplace/webhook/xixapay',
                    ];
                } else {
                    // Option 2: new customer with raw data
                    $xixaPayload = [
                        'email' => $user->email,
                        'name' => $user->name ?? $user->username,
                        'phoneNumber' => $user->phone ?? '08000000000',
                        'bankCode' => $bankCode,
                        'businessId' => $xixa['business_id'],
                        'accountType' => 'dynamic',
                        'amount' => $grandTotal,
                        'externalReference' => $reference,
                        'callbackUrl' => url('') . '/api/marketplace/webhook/xixapay',
                    ];
                }

                $xixaResponse = \Illuminate\Support\Facades\Http::timeout(30)->withHeaders([
                    'Authorization' => $xixa['authorization'],
                    'api-key' => $xixa['api_key'],
                    'Content-Type' => 'application/json',
                ])->post('https://api.xixapay.com/api/v1/createVirtualAccount', $xixaPayload);

                $xixaData = $xixaResponse->json();
                Log::info('Xixapay dynamic account response', ['bankCode' => $bankCode, 'data' => $xixaData, 'order' => $reference]);

                // Save customer_id for future use
                if (!empty($xixaData['customer']['customer_id']) && !$existingCustomerId) {
                    try {
                        DB::table('user')->where('id', $user_id)->update([
                            'xixapay_customer_id' => $xixaData['customer']['customer_id'],
                        ]);
                        $existingCustomerId = $xixaData['customer']['customer_id'];
                    } catch (\Exception $e) {
                        // Column may not exist yet — ignore
                    }
                }

                // Got accounts — stop trying
                if ($xixaResponse->successful() && !empty($xixaData['bankAccounts'])) {
                    break;
                }
            }

            if ($xixaResponse->successful() && !empty($xixaData['bankAccounts'])) {
                $account = $xixaData['bankAccounts'][0];
                $order->update(['payment_reference' => $reference]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Order created. Complete payment.',
                    'data' => [
                        'payment_provider' => 'xixapay',
                        'payment_type' => 'bank_transfer',
                        'reference' => $reference,
                        'order_reference' => $reference,
                        'order_id' => $order->id,
                        'amount' => $grandTotal,
                        'grand_total' => $grandTotal,
                        'account_number' => $account['accountNumber'],
                        'account_name' => $account['accountName'],
                        'bank_name' => $account['bankName'],
                        'bank_code' => $account['bankCode'],
                        'expires_in' => '30 minutes',
                        'message' => 'Transfer exactly ₦' . number_format($grandTotal, 2) . ' to the account below to complete your order.',
                    ],
                ]);
            }

            // Dynamic failed — fallback to user's existing static account
            Log::warning('Xixapay dynamic empty, falling back to static account', ['order' => $reference]);
            $staticAccount = $user->kolomoni_mfb ?? $user->palmpay ?? null;
            $staticBank = $user->kolomoni_mfb ? 'Kolomoni MFB' : ($user->palmpay ? 'PalmPay' : null);

            if ($staticAccount) {
                $order->update(['payment_reference' => $reference]);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Order created. Complete payment.',
                    'data' => [
                        'payment_provider' => 'xixapay',
                        'payment_type' => 'bank_transfer',
                        'reference' => $reference,
                        'order_reference' => $reference,
                        'order_id' => $order->id,
                        'amount' => $grandTotal,
                        'grand_total' => $grandTotal,
                        'account_number' => $staticAccount,
                        'account_name' => 'VendLike - ' . strtoupper($user->username),
                        'bank_name' => $staticBank,
                        'expires_in' => '60 minutes',
                        'message' => 'Transfer EXACTLY ₦' . number_format($grandTotal, 2) . ' to the account below. Use order ref ' . $reference . ' as narration.',
                        'important' => 'Transfer the exact amount. Admin will verify and confirm your order.',
                    ],
                ]);
            }

            // No account at all — delete order
            MarketplaceOrderItem::where('order_id', $order->id)->delete();
            $order->delete();
            Log::error('Xixapay all bank codes failed and no static fallback', ['last_response' => $xixaData, 'order' => $reference]);
            return response()->json(['status' => 'fail', 'message' => 'Payment service unavailable. Please try again.'], 500);
        }

        // ── POINTWAVE DYNAMIC ACCOUNT ────────────────────────────────
        if ($paymentProvider === 'pointwave') {
            $pwSecretKey = env('POINTWAVE_SECRET_KEY');
            $pwApiKey = env('POINTWAVE_API_KEY');
            $pwBusinessId = env('POINTWAVE_BUSINESS_ID');
            $pwBaseUrl = env('POINTWAVE_BASE_URL', 'https://app.pointwave.ng/api/v1');

            $pwPayload = json_encode([
                'amount' => (int) round($grandTotal * 100), // kobo
                'currency' => 'NGN',
                'title' => 'Marketplace Order ' . $reference,
                'callback_url' => url('') . '/api/marketplace/webhook/pointwave',
                'reference' => $reference,
                'order_expire_time' => 1800,
            ]);

            $pwCh = curl_init();
            curl_setopt_array($pwCh, [
                CURLOPT_URL => $pwBaseUrl . '/checkout/bank-transfer/create',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $pwPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $pwSecretKey,
                    'x-business-id: ' . $pwBusinessId,
                    'x-api-key: ' . $pwApiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $pwResult = curl_exec($pwCh);
            $pwHttpCode = curl_getinfo($pwCh, CURLINFO_HTTP_CODE);
            curl_close($pwCh);

            $pwData = json_decode($pwResult, true);
            // PointWave wraps response in a 'data' key
            $pwPayload = $pwData['data'] ?? $pwData;
            Log::info('PointWave dynamic account response', ['data' => $pwData, 'order' => $reference]);

            if ($pwHttpCode === 200 && ($pwPayload['status'] ?? '') === 'success' && !empty($pwPayload['account_number'])) {
                $order->update([
                    'payment_reference' => $reference,
                    'pointwave_order_id' => $pwPayload['order_id'] ?? null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Order created. Complete payment.',
                    'data' => [
                        'payment_provider' => 'pointwave',
                        'payment_type' => 'bank_transfer',
                        'reference' => $reference,
                        'order_reference' => $reference,
                        'order_id' => $order->id,
                        'pointwave_order_id' => $pwPayload['order_id'] ?? null,
                        'amount' => $grandTotal,
                        'grand_total' => $grandTotal,
                        'account_number' => $pwPayload['account_number'],
                        'account_name' => $pwPayload['account_name'],
                        'bank_name' => $pwPayload['bank_name'],
                        'expires_in' => '30 minutes',
                        'message' => 'Transfer exactly ₦' . number_format($grandTotal, 2) . ' to the account below to complete your order.',
                    ],
                ]);
            }

            // PointWave failed — delete order
            MarketplaceOrderItem::where('order_id', $order->id)->delete();
            $order->delete();
            Log::error('PointWave dynamic account failed', ['response' => $pwData, 'http' => $pwHttpCode]);
            return response()->json(['status' => 'fail', 'message' => 'Payment service unavailable. Please try again.'], 500);
        }

        // ── MONNIFY CHECKOUT ─────────────────────────────────────────
        $habukhan_key = DB::table('habukhan_key')->first();
        $post_data = [
            'amount' => $grandTotal,
            'customerName' => $user->name ?? $user->username,
            'customerEmail' => $user->email,
            'paymentReference' => $reference,
            'paymentDescription' => 'Marketplace Order - ' . count($orderItems) . ' item(s)',
            'currencyCode' => 'NGN',
            'contractCode' => $habukhan_key->mon_con_num,
            'redirectUrl' => url('') . '/api/marketplace/payment/callback',
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'],
            'metadata' => [
                'order_reference' => $reference,
                'user_id' => $user_id,
            ],
        ];

        $url = 'https://api.monnify.com/api/v1/merchant/transactions/init-transaction';

        // Step 1: Get Bearer token from Monnify
        $authCh = curl_init();
        curl_setopt_array($authCh, [
            CURLOPT_URL => 'https://api.monnify.com/api/v1/auth/login',
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($habukhan_key->mon_app_key . ':' . $habukhan_key->mon_sk_key),
            ],
        ]);
        $authResult = json_decode(curl_exec($authCh), true);
        curl_close($authCh);

        $accessToken = $authResult['responseBody']['accessToken'] ?? null;
        if (!$accessToken) {
            MarketplaceOrderItem::where('order_id', $order->id)->delete();
            $order->delete();
            Log::error('Monnify auth failed for marketplace', ['response' => $authResult]);
            return response()->json(['status' => 'fail', 'message' => 'Payment service unavailable. Please try again.'], 500);
        }

        // Step 2: Init transaction with Bearer token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $get_res = curl_exec($ch);
        curl_close($ch);
        $monnifyResponse = json_decode($get_res, true);

        if ($monnifyResponse && isset($monnifyResponse['responseBody']['checkoutUrl'])) {
            $monnifyRef = $monnifyResponse['responseBody']['transactionReference'];
            $order->update([
                'monnify_reference' => $monnifyRef,
                'payment_reference' => $reference,
            ]);

            // Record pending transaction in history so user can see it immediately
            $itemNames = collect($orderItems)->pluck('product_name')->toArray();
            DB::table('message')->insert([
                'username' => $user->username ?? 'unknown',
                'message' => '🛒 Marketplace Order - ' . implode(', ', array_slice($itemNames, 0, 2)) . (count($itemNames) > 2 ? '...' : '') . "\n\n⏳ Awaiting payment confirmation. Total: ₦" . number_format($grandTotal, 2),
                'amount' => $grandTotal,
                'oldbal' => $user->bal ?? 0,
                'newbal' => $user->bal ?? 0,
                'habukhan_date' => Carbon::now(),
                'transid' => $reference,
                'plan_status' => 0,
                'role' => 'marketplace',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order created. Complete payment.',
                'data' => [
                    'reference' => $reference,
                    'total_amount' => $totalAmount,
                    'delivery_fee' => $deliveryFee,
                    'delivery_eta' => $deliveryEta,
                    'grand_total' => $grandTotal,
                    'items_count' => count($orderItems),
                    'checkout_url' => $monnifyResponse['responseBody']['checkoutUrl'],
                    'monnify_reference' => $monnifyRef,
                    'payment_method' => 'monnify',
                    // For Flutter SDK
                    'monnify_api_key' => $habukhan_key->mon_app_key,
                    'monnify_contract_code' => $habukhan_key->mon_con_num,
                    'customer_name' => $user->name ?? $user->username,
                    'customer_email' => $user->email,
                ],
            ]);
        } else {
            // Monnify init failed — delete the pending order
            MarketplaceOrderItem::where('order_id', $order->id)->delete();
            $order->delete();
            Log::error('Monnify init failed for marketplace', ['response' => $monnifyResponse]);
            return response()->json(['status' => 'fail', 'message' => 'Payment initialization failed. Please try again.'], 500);
        }
    }

    // ─── MOBILE: ORDER HISTORY ───

    public function getOrders(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $orders = MarketplaceOrder::with('items')
            ->where('user_id', $user_id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    public function getOrder(Request $request, $reference)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $order = MarketplaceOrder::with('items')
            ->where('user_id', $user_id)
            ->where('reference', $reference)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $order]);
    }

    public function repayOrder(Request $request, $reference)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $user = DB::table('user')->where('id', $user_id)->first();
        if (!$user) {
            return response()->json(['status' => 'fail', 'message' => 'User not found'], 404);
        }

        $order = MarketplaceOrder::with('items')
            ->where('user_id', $user_id)
            ->where('reference', $reference)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 'fail', 'message' => 'Order is already paid.'], 400);
        }
        if (in_array(strtolower($order->status), ['cancelled', 'delivered'])) {
            return response()->json(['status' => 'fail', 'message' => 'Order cannot be paid for in its current state.'], 400);
        }

        $grandTotal = (float) $order->grand_total;
        $orderItemsCount = $order->items->count();

        // Use current system provider or fallback to Xixapay
        $paymentProvider = DB::table('settings')->value('marketplace_payment_provider') ?? 'xixapay';

        // Update the order's payment method so it reflects how they are paying NOW
        $order->update(['payment_method' => $paymentProvider]);

        if ($paymentProvider === 'xixapay') {
            $xixa = config('services.xixapay');
            $bankCodesToTry = [['20867'], ['20987'], ['29007']];
            $xixaData = null;
            $xixaResponse = null;
            $existingCustomerId = $user->xixapay_customer_id ?? null;

            foreach ($bankCodesToTry as $bankCode) {
                if ($existingCustomerId) {
                    $xixaPayload = [
                        'customer_id' => $existingCustomerId,
                        'bankCode' => $bankCode,
                        'businessId' => $xixa['business_id'],
                        'accountType' => 'dynamic',
                        'amount' => $grandTotal,
                        'externalReference' => $reference,
                        'callbackUrl' => url('') . '/api/marketplace/webhook/xixapay',
                    ];
                } else {
                    $xixaPayload = [
                        'email' => $user->email,
                        'name' => $user->name ?? $user->username,
                        'phoneNumber' => $user->phone ?? '08000000000',
                        'bankCode' => $bankCode,
                        'businessId' => $xixa['business_id'],
                        'accountType' => 'dynamic',
                        'amount' => $grandTotal,
                        'externalReference' => $reference,
                        'callbackUrl' => url('') . '/api/marketplace/webhook/xixapay',
                    ];
                }

                $xixaResponse = \Illuminate\Support\Facades\Http::timeout(30)->withHeaders([
                    'Authorization' => $xixa['authorization'],
                    'api-key' => $xixa['api_key'],
                    'Content-Type' => 'application/json',
                ])->post('https://api.xixapay.com/api/v1/createVirtualAccount', $xixaPayload);

                $xixaData = $xixaResponse->json();

                if (!empty($xixaData['customer']['customer_id']) && !$existingCustomerId) {
                    try {
                        DB::table('user')->where('id', $user_id)->update([
                            'xixapay_customer_id' => $xixaData['customer']['customer_id'],
                        ]);
                        $existingCustomerId = $xixaData['customer']['customer_id'];
                    } catch (\Exception $e) {
                    }
                }

                if ($xixaResponse->successful() && !empty($xixaData['bankAccounts'])) {
                    break;
                }
            }

            if ($xixaResponse->successful() && !empty($xixaData['bankAccounts'])) {
                $account = $xixaData['bankAccounts'][0];
                $order->update(['payment_reference' => $reference]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Please complete your payment.',
                    'data' => [
                        'payment_provider' => 'xixapay',
                        'payment_type' => 'bank_transfer',
                        'reference' => $reference,
                        'order_reference' => $reference,
                        'order_id' => $order->id,
                        'amount' => $grandTotal,
                        'grand_total' => $grandTotal,
                        'account_number' => $account['accountNumber'],
                        'account_name' => $account['accountName'],
                        'bank_name' => $account['bankName'],
                        'bank_code' => $account['bankCode'],
                        'expires_in' => '30 minutes',
                        'message' => 'Transfer exactly ₦' . number_format($grandTotal, 2) . ' to the account below.',
                    ],
                ]);
            }

            // Fallback to static
            $staticAccount = $user->kolomoni_mfb ?? $user->palmpay ?? null;
            $staticBank = $user->kolomoni_mfb ? 'Kolomoni MFB' : ($user->palmpay ? 'PalmPay' : null);

            if ($staticAccount) {
                $order->update(['payment_reference' => $reference]);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Please complete your payment.',
                    'data' => [
                        'payment_provider' => 'xixapay',
                        'payment_type' => 'bank_transfer',
                        'reference' => $reference,
                        'order_reference' => $reference,
                        'order_id' => $order->id,
                        'amount' => $grandTotal,
                        'grand_total' => $grandTotal,
                        'account_number' => $staticAccount,
                        'account_name' => 'VendLike - ' . strtoupper($user->username),
                        'bank_name' => $staticBank,
                        'expires_in' => '60 minutes',
                        'message' => 'Transfer EXACTLY ₦' . number_format($grandTotal, 2) . ' to the account below. Use order ref ' . $reference . ' as narration.',
                        'important' => 'Transfer the exact amount. Admin will verify and confirm your order.',
                    ],
                ]);
            }

            Log::error('Xixapay repay all bank codes failed and no static fallback', ['order' => $reference]);
            return response()->json(['status' => 'fail', 'message' => 'Payment service unavailable. Please try again later.'], 500);
        }

        if ($paymentProvider === 'pointwave') {
            $pwSecretKey = env('POINTWAVE_SECRET_KEY');
            $pwApiKey = env('POINTWAVE_API_KEY');
            $pwBusinessId = env('POINTWAVE_BUSINESS_ID');
            $pwBaseUrl = env('POINTWAVE_BASE_URL', 'https://app.pointwave.ng/api/v1');

            $pwPayload = json_encode([
                'amount' => (int) round($grandTotal * 100),
                'currency' => 'NGN',
                'title' => 'Marketplace Order ' . $reference,
                'callback_url' => url('') . '/api/marketplace/webhook/pointwave',
                'reference' => $reference,
                'order_expire_time' => 1800,
            ]);

            $pwCh = curl_init();
            curl_setopt_array($pwCh, [
                CURLOPT_URL => $pwBaseUrl . '/checkout/bank-transfer/create',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $pwPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $pwSecretKey,
                    'x-business-id: ' . $pwBusinessId,
                    'x-api-key: ' . $pwApiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $pwResult = curl_exec($pwCh);
            $pwHttpCode = curl_getinfo($pwCh, CURLINFO_HTTP_CODE);
            curl_close($pwCh);

            $pwData = json_decode($pwResult, true);
            $pwPayload = $pwData['data'] ?? $pwData;

            if ($pwHttpCode === 200 && ($pwPayload['status'] ?? '') === 'success' && !empty($pwPayload['account_number'])) {
                $order->update([
                    'payment_reference' => $reference,
                    'pointwave_order_id' => $pwPayload['order_id'] ?? null,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Please complete your payment.',
                    'data' => [
                        'payment_provider' => 'pointwave',
                        'payment_type' => 'bank_transfer',
                        'reference' => $reference,
                        'order_reference' => $reference,
                        'order_id' => $order->id,
                        'pointwave_order_id' => $pwPayload['order_id'] ?? null,
                        'amount' => $grandTotal,
                        'grand_total' => $grandTotal,
                        'account_number' => $pwPayload['account_number'],
                        'account_name' => $pwPayload['account_name'],
                        'bank_name' => $pwPayload['bank_name'],
                        'expires_in' => '30 minutes',
                        'message' => 'Transfer exactly ₦' . number_format($grandTotal, 2) . ' to the account below.',
                    ],
                ]);
            }

            Log::error('PointWave dynamic account failed during repay', ['response' => $pwData, 'http' => $pwHttpCode]);
            return response()->json(['status' => 'fail', 'message' => 'Payment service unavailable. Please try again later.'], 500);
        }

        // Monnify
        $habukhan_key = DB::table('habukhan_key')->first();
        $post_data = [
            'amount' => $grandTotal,
            'customerName' => $user->name ?? $user->username,
            'customerEmail' => $user->email,
            'paymentReference' => $reference,
            'paymentDescription' => 'Marketplace Order - ' . $orderItemsCount . ' item(s)',
            'currencyCode' => 'NGN',
            'contractCode' => $habukhan_key->mon_con_num,
            'redirectUrl' => url('') . '/api/marketplace/payment/callback',
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'],
            'metadata' => [
                'order_reference' => $reference,
                'user_id' => $user_id,
            ],
        ];

        $url = 'https://api.monnify.com/api/v1/merchant/transactions/init-transaction';

        $authCh = curl_init();
        curl_setopt_array($authCh, [
            CURLOPT_URL => 'https://api.monnify.com/api/v1/auth/login',
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($habukhan_key->mon_app_key . ':' . $habukhan_key->mon_sk_key),
            ],
        ]);
        $authResult = json_decode(curl_exec($authCh), true);
        curl_close($authCh);

        $accessToken = $authResult['responseBody']['accessToken'] ?? null;
        if (!$accessToken) {
            Log::error('Monnify auth failed for marketplace repay', ['response' => $authResult]);
            return response()->json(['status' => 'fail', 'message' => 'Payment service unavailable. Please try again later.'], 500);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $get_res = curl_exec($ch);
        curl_close($ch);
        $monnifyResponse = json_decode($get_res, true);

        if ($monnifyResponse && isset($monnifyResponse['responseBody']['checkoutUrl'])) {
            $monnifyRef = $monnifyResponse['responseBody']['transactionReference'];
            $order->update([
                'monnify_reference' => $monnifyRef,
                'payment_reference' => $reference,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Please complete your payment.',
                'data' => [
                    'reference' => $reference,
                    'grand_total' => $grandTotal,
                    'checkout_url' => $monnifyResponse['responseBody']['checkoutUrl'],
                    'monnify_reference' => $monnifyRef,
                    'payment_method' => 'monnify',
                    'monnify_api_key' => $habukhan_key->mon_app_key,
                    'monnify_contract_code' => $habukhan_key->mon_con_num,
                    'customer_name' => $user->name ?? $user->username,
                    'customer_email' => $user->email,
                ],
            ]);
        } else {
            Log::error('Monnify init failed for marketplace repay', ['response' => $monnifyResponse]);
            return response()->json(['status' => 'fail', 'message' => 'Payment initialization failed. Please try again later.'], 500);
        }
    }

    // ─── MOBILE: DELIVERY COST ───

    public function getDeliveryCost(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'state' => 'required|string',
            'weight' => 'nullable|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'fail', 'message' => $validator->errors()->first()], 400);
        }

        // Smart shipping weight: cap at max 5kg for Fez calculation
        // This prevents unrealistic delivery costs for bulk lightweight items
        $rawWeight = (float) ($request->weight ?? 1);
        $maxShippingWeight = 5.0;
        $shippingWeight = min($rawWeight, $maxShippingWeight);
        // Minimum 0.5kg for Fez (some carriers have minimums)
        $shippingWeight = max($shippingWeight, 0.5);

        $settings = DB::table('settings')->first();
        $deliveryMode = $settings->marketplace_delivery_mode ?? 'self';

        // If admin chose "self" delivery, return the flat fee
        if ($deliveryMode === 'self') {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'state' => $request->state,
                    'base_cost' => (float) ($settings->marketplace_delivery_fee ?? 0),
                    'vat' => 0,
                    'total_cost' => (float) ($settings->marketplace_delivery_fee ?? 0),
                    'eta' => null,
                    'mode' => 'self',
                ],
            ]);
        }

        // Fez delivery mode
        try {
            $fez = new FezDeliveryService();
            $costResult = $fez->getDeliveryCost($request->state, $shippingWeight);
            $etaResult = $fez->getDeliveryEstimate('Lagos', $request->state);

            $totalCost = isset($costResult['totalCost']) ? (float) $costResult['totalCost'] : 0;
            $baseCost = isset($costResult['cost']['cost']) ? (float) $costResult['cost']['cost'] : 0;
            $vat = isset($costResult['vat']['vatAmount']) ? (float) $costResult['vat']['vatAmount'] : 0;
            $eta = $etaResult['data']['eta'] ?? null;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'state' => $request->state,
                    'base_cost' => $baseCost,
                    'vat' => $vat,
                    'total_cost' => $totalCost,
                    'eta' => $eta,
                    'mode' => 'fez',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Fez delivery cost error', ['error' => $e->getMessage()]);
            // Fallback to flat fee
            return response()->json([
                'status' => 'success',
                'data' => [
                    'state' => $request->state,
                    'base_cost' => (float) ($settings->marketplace_delivery_fee ?? 0),
                    'vat' => 0,
                    'total_cost' => (float) ($settings->marketplace_delivery_fee ?? 0),
                    'eta' => null,
                    'mode' => 'self_fallback',
                ],
            ]);
        }
    }

    // ─── MOBILE: TRACK ORDER ───

    public function trackOrder(Request $request, $reference)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $order = MarketplaceOrder::where('user_id', $user_id)->where('reference', $reference)->first();
        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        if (!$order->fez_order_no) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_status' => $order->status,
                    'delivery_status' => $order->delivery_status ?? 'pending',
                    'timeline' => [],
                ],
            ]);
        }

        try {
            $fez = new FezDeliveryService();
            $tracking = $fez->trackOrder($order->fez_order_no);

            $timeline = [];
            if (isset($tracking['history']) && is_array($tracking['history'])) {
                $timeline = array_map(function ($h) {
                    return [
                        'status' => $h['orderStatus'] ?? '',
                        'date' => $h['statusCreationDate'] ?? '',
                        'description' => $h['statusDescription'] ?? '',
                    ];
                }, $tracking['history']);
            }

            // Update local delivery status
            if (isset($tracking['order']['orderStatus'])) {
                $fezStatus = $tracking['order']['orderStatus'];
                $mappedStatus = $this->mapFezStatus($fezStatus);
                $order->update([
                    'delivery_status' => $fezStatus,
                    'status' => $mappedStatus,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_status' => $order->status,
                    'delivery_status' => $tracking['order']['orderStatus'] ?? $order->delivery_status,
                    'fez_order_no' => $order->fez_order_no,
                    'timeline' => $timeline,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Fez tracking error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_status' => $order->status,
                    'delivery_status' => $order->delivery_status ?? 'pending',
                    'timeline' => [],
                ],
            ]);
        }
    }

    // ─── ADMIN: TRACK ORDER ───

    public function adminTrackOrder(Request $request, $id)
    {
        $token = $request->route('id') ?? $request->query('token');
        $verified_id = $this->verifytoken($token);
        if (!$verified_id || !$this->isAdmin($verified_id)) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $orderId = $request->route('orderId') ?? $id;
        $order = MarketplaceOrder::find($orderId);
        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        if (!$order->fez_order_no) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_status' => $order->status,
                    'delivery_status' => $order->delivery_status ?? 'pending',
                    'timeline' => [],
                ],
            ]);
        }

        try {
            $fez = new FezDeliveryService();
            $tracking = $fez->trackOrder($order->fez_order_no);

            $timeline = [];
            if (isset($tracking['history']) && is_array($tracking['history'])) {
                $timeline = array_map(function ($h) {
                    return [
                        'status' => $h['orderStatus'] ?? '',
                        'date' => $h['statusCreationDate'] ?? '',
                        'description' => $h['statusDescription'] ?? '',
                    ];
                }, $tracking['history']);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_status' => $order->status,
                    'delivery_status' => $tracking['order']['orderStatus'] ?? $order->delivery_status,
                    'fez_order_no' => $order->fez_order_no,
                    'timeline' => $timeline,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_status' => $order->status,
                    'delivery_status' => $order->delivery_status ?? 'pending',
                    'timeline' => [],
                ],
            ]);
        }
    }

    // ─── PAYMENT: MONNIFY CALLBACK (redirect after payment) ───

    public function paymentCallback(Request $request)
    {
        $paymentRef = $request->paymentReference ?? $request->query('paymentReference');
        if (!$paymentRef) {
            return redirect(config('app.app_url') . '/marketplace/payment-failed');
        }

        $order = MarketplaceOrder::where('reference', $paymentRef)
            ->orWhere('monnify_reference', $paymentRef)
            ->first();

        if (!$order || $order->payment_status === 'paid') {
            return redirect(config('app.app_url') . '/marketplace/orders');
        }

        // Verify payment with Monnify
        $habukhan_key = DB::table('habukhan_key')->first();
        $monnifyRef = $order->monnify_reference ?? $paymentRef;

        $verified = $this->verifyMonnifyPayment($monnifyRef, $habukhan_key);

        if ($verified && $verified['paymentStatus'] === 'PAID') {
            $this->completeOrder($order);
            return redirect(config('app.app_url') . '/marketplace/payment-success?ref=' . $order->reference);
        } else {
            return redirect(config('app.app_url') . '/marketplace/payment-failed?ref=' . $order->reference);
        }
    }

    // ─── PAYMENT: VERIFY PAYMENT (mobile calls after SDK completes) ───

    public function verifyPayment(Request $request)
    {
        $user_id = $this->verifyapptoken($request->header('Authorization'));
        if (!$user_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unauthorized'], 401);
        }

        $reference = $request->reference;
        if (!$reference) {
            return response()->json(['status' => 'fail', 'message' => 'Reference required'], 400);
        }

        $order = MarketplaceOrder::where('reference', $reference)
            ->where('user_id', $user_id)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'fail', 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 'success', 'message' => 'Payment already confirmed', 'data' => ['reference' => $order->reference, 'payment_status' => 'paid', 'status' => $order->status]]);
        }

        // ── Already paid via webhook ─────────────────────────────────
        // Check if PointWave webhook already confirmed it
        $order->refresh();
        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 'success', 'message' => 'Payment confirmed.', 'data' => ['reference' => $order->reference, 'payment_status' => 'paid', 'status' => $order->status]]);
        }

        // ── PointWave: query order status ────────────────────────────
        if ($order->payment_method === 'pointwave' && $order->pointwave_order_id) {
            $pwSecretKey = env('POINTWAVE_SECRET_KEY');
            $pwApiKey = env('POINTWAVE_API_KEY');
            $pwBusinessId = env('POINTWAVE_BUSINESS_ID');
            $pwBaseUrl = env('POINTWAVE_BASE_URL', 'https://app.pointwave.ng/api/v1');

            $pwCh = curl_init();
            curl_setopt_array($pwCh, [
                CURLOPT_URL => $pwBaseUrl . '/checkout/bank-transfer/query',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['order_id' => $order->pointwave_order_id]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $pwSecretKey,
                    'x-business-id: ' . $pwBusinessId,
                    'x-api-key: ' . $pwApiKey,
                    'Content-Type: application/json',
                ],
            ]);
            $pwResult = curl_exec($pwCh);
            curl_close($pwCh);
            $pwData = json_decode($pwResult, true);
            $pwStatus = $pwData['data']['orderStatus'] ?? $pwData['orderStatus'] ?? null;
            Log::info('PointWave verify query', ['order_id' => $order->pointwave_order_id, 'status' => $pwStatus]);

            // PointWave status 2 = success
            if ($pwStatus === 2 || $pwStatus === '2') {
                $this->completeOrder($order);
                $order->refresh();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment confirmed.',
                    'data' => ['reference' => $order->reference, 'payment_status' => 'paid', 'status' => $order->status],
                ]);
            }

            // Not paid yet
            return response()->json([
                'status' => 'success',
                'message' => 'Payment not yet confirmed.',
                'data' => ['reference' => $order->reference, 'payment_status' => 'pending', 'status' => $order->status],
            ]);
        }

        $habukhan_key = DB::table('habukhan_key')->first();
        $monnifyRef = $order->monnify_reference ?? $reference;
        $verified = $this->verifyMonnifyPayment($monnifyRef, $habukhan_key);

        if ($verified && $verified['paymentStatus'] === 'PAID') {
            $this->completeOrder($order);
            return response()->json([
                'status' => 'success',
                'message' => 'Payment confirmed. Order is being processed.',
                'data' => [
                    'reference' => $order->reference,
                    'status' => 'processing',
                    'fez_order_no' => $order->fez_order_no,
                    'delivery_eta' => $order->delivery_eta,
                ],
            ]);
        } else {
            // Payment not yet confirmed — return pending status (not error)
            return response()->json([
                'status' => 'success',
                'message' => 'Payment not yet confirmed by Monnify.',
                'data' => [
                    'reference' => $order->reference,
                    'payment_status' => 'pending',
                    'status' => $order->status,
                ],
            ]);
        }
    }

    // ─── PAYMENT: MONNIFY WEBHOOK ───

    public function monnifyWebhook(Request $request)
    {
        if (!$request->eventData || $request->eventType !== 'SUCCESSFUL_TRANSACTION') {
            return response()->json(['status' => 'ok']);
        }

        // Verify hash
        $habukhan_key = DB::table('habukhan_key')->first();
        $signature = $request->header('monnify-signature');
        $computedHash = hash_hmac('sha512', $request->getContent(), $habukhan_key->mon_sk_key);

        if ($signature && $signature !== $computedHash) {
            Log::warning('Marketplace webhook hash mismatch');
            return response()->json(['status' => 'fail'], 400);
        }

        $eventData = $request->eventData;
        $paymentRef = $eventData['paymentReference'] ?? null;
        $paymentStatus = $eventData['paymentStatus'] ?? null;

        if (!$paymentRef || $paymentStatus !== 'PAID') {
            return response()->json(['status' => 'ok']);
        }

        // Find order by payment reference (our MP_ reference)
        $order = MarketplaceOrder::where('reference', $paymentRef)->first();
        if (!$order) {
            // Try monnify_reference
            $monnifyRef = $eventData['transactionReference'] ?? null;
            if ($monnifyRef) {
                $order = MarketplaceOrder::where('monnify_reference', $monnifyRef)->first();
            }
        }

        if (!$order || $order->payment_status === 'paid') {
            return response()->json(['status' => 'ok']);
        }

        $this->completeOrder($order);
        return response()->json(['status' => 'ok']);
    }

    // ─── PAYMENT: XIXAPAY WEBHOOK ───

    public function xixapayWebhook(Request $request)
    {
        Log::info('Xixapay marketplace webhook received', ['data' => $request->all()]);

        // Xixapay sends payment notification with externalReference = our order reference
        $externalRef = $request->externalReference ?? $request->reference ?? null;
        $status = $request->status ?? $request->paymentStatus ?? null;
        $amount = (float) ($request->amount ?? 0);

        if (!$externalRef) {
            return response()->json(['status' => 'ok']);
        }

        $order = MarketplaceOrder::where('reference', $externalRef)->first();
        if (!$order || $order->payment_status === 'paid') {
            return response()->json(['status' => 'ok']);
        }

        // Accept if status is success/paid and amount matches (allow small tolerance)
        $isPaid = in_array(strtolower($status ?? ''), ['success', 'successful', 'paid', 'completed']);
        $amountMatch = $amount >= ($order->grand_total - 1); // 1 naira tolerance

        if ($isPaid && $amountMatch) {
            $this->completeOrder($order);
            Log::info('Xixapay marketplace order completed', ['reference' => $externalRef]);
        } else {
            Log::warning('Xixapay webhook: payment not confirmed', ['status' => $status, 'amount' => $amount, 'expected' => $order->grand_total]);
        }

        return response()->json(['status' => 'ok']);
    }

    // ─── PAYMENT: POINTWAVE WEBHOOK ───

    public function pointwaveWebhook(Request $request)
    {
        Log::info('PointWave marketplace webhook received', ['data' => $request->all()]);

        // PointWave sends reference = our order reference
        $reference = $request->reference ?? $request->order_reference ?? $request->input('data.reference') ?? null;
        $status = strtolower($request->status ?? $request->payment_status ?? $request->input('data.status') ?? '');
        $amount = (float) ($request->amount ?? $request->input('data.amount') ?? 0);

        if (!$reference) {
            return response('success', 200)->header('Content-Type', 'text/plain');
        }

        $order = MarketplaceOrder::where('reference', $reference)->first();
        if (!$order || $order->payment_status === 'paid') {
            return response('success', 200)->header('Content-Type', 'text/plain');
        }

        $isPaid = in_array($status, ['success', 'successful', 'paid', 'completed']);
        // Amount from PointWave is in kobo — convert to naira
        $amountNaira = $amount > 1000 ? $amount / 100 : $amount;
        $amountMatch = $amountNaira >= ($order->grand_total - 1);

        if ($isPaid && $amountMatch) {
            $this->completeOrder($order);
            Log::info('PointWave marketplace order completed', ['reference' => $reference]);
        } else {
            Log::warning('PointWave webhook: payment not confirmed', ['status' => $status, 'amount' => $amountNaira, 'expected' => $order->grand_total]);
        }

        // PointWave requires plain text "success" response
        return response('success', 200)->header('Content-Type', 'text/plain');
    }

    // ─── HELPERS ───

    /**
     * Complete order after payment confirmed: deduct stock, book Fez, record transaction
     */
    private function completeOrder(MarketplaceOrder $order)
    {
        if ($order->payment_status === 'paid')
            return;

        try {
            DB::beginTransaction();

            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
            ]);

            // Deduct stock
            $items = MarketplaceOrderItem::where('order_id', $order->id)->get();
            foreach ($items as $item) {
                MarketplaceProduct::where('id', $item->product_id)
                    ->where('stock', '>=', $item->quantity)
                    ->decrement('stock', $item->quantity);
            }

            // Update transaction record from pending → success
            $user = DB::table('user')->where('id', $order->user_id)->first();
            $itemNames = $items->pluck('product_name')->toArray();
            $updated = DB::table('message')
                ->where('transid', $order->reference)
                ->where('role', 'marketplace')
                ->update([
                    'message' => '🛒 Marketplace Order (Paid) - ' . implode(', ', array_slice($itemNames, 0, 2)) . (count($itemNames) > 2 ? '...' : '') . "\n\n✅ Payment confirmed. Order is being processed.",
                    'plan_status' => 1,
                    'habukhan_date' => Carbon::now(),
                ]);

            // If no existing record found (edge case), insert one
            if (!$updated) {
                DB::table('message')->insert([
                    'username' => $user->username ?? 'unknown',
                    'message' => '🛒 Marketplace Order (Paid) - ' . implode(', ', array_slice($itemNames, 0, 2)) . (count($itemNames) > 2 ? '...' : ''),
                    'amount' => $order->grand_total,
                    'oldbal' => $user->bal ?? 0,
                    'newbal' => $user->bal ?? 0,
                    'habukhan_date' => Carbon::now(),
                    'transid' => $order->reference,
                    'plan_status' => 1,
                    'role' => 'marketplace',
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Complete order failed', ['error' => $e->getMessage(), 'order' => $order->reference]);
            return;
        }

        // Book Fez delivery (outside transaction — non-critical)
        $this->bookFezDelivery($order);

        // Push notification
        try {
            $user = DB::table('user')->where('id', $order->user_id)->first();
            if ($user) {
                \App\Helpers\NotificationHelper::sendTransactionNotification(
                    $user,
                    'debit',
                    $order->grand_total,
                    'Marketplace Order - ' . $items->count() . ' item(s)',
                    $order->reference
                );
            }
        } catch (\Exception $e) {
            Log::error('Marketplace push notification failed', ['error' => $e->getMessage()]);
        }

        // Send order confirmation email
        try {
            $user = $user ?? DB::table('user')->where('id', $order->user_id)->first();
            if ($user && !empty($user->email)) {
                $emailItems = [];
                foreach ($items as $item) {
                    $emailItems[] = [
                        'name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'size' => $item->size ?? null,
                        'color' => $item->color ?? null,
                        'subtotal' => $item->subtotal,
                    ];
                }
                $emailData = [
                    'email' => $user->email,
                    'username' => $user->username,
                    'title' => '🛒 Order Confirmed - ' . $order->reference . ' | ' . config('app.name'),
                    'reference' => $order->reference,
                    'total_amount' => $order->total_amount,
                    'delivery_fee' => $order->delivery_fee,
                    'grand_total' => $order->grand_total,
                    'date' => now()->format('d M Y, h:i A'),
                    'items' => $emailItems,
                    'delivery_name' => $order->delivery_name,
                    'delivery_phone' => $order->delivery_phone,
                    'delivery_address' => $order->delivery_address,
                    'delivery_state' => $order->delivery_state,
                    'delivery_eta' => $order->delivery_eta,
                ];
                $pdfData = array_merge($emailData, [
                    'invoice_type' => 'ORDER INVOICE',
                    'status' => 'CONFIRMED',
                    'customer_name' => $user->name ?? $user->username,
                    'customer_email' => $user->email,
                    'customer_phone' => $order->delivery_phone,
                ]);
                $attachment = \App\Services\InvoiceService::generatePdf('MARKETPLACE', $pdfData);
                \App\Http\Controllers\MailController::send_mail($emailData, 'email.order_confirmed', $attachment);
            }
        } catch (\Exception $e) {
            Log::error('Marketplace order confirmation email failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Book delivery with Fez
     */
    private function bookFezDelivery(MarketplaceOrder $order)
    {
        try {
            $fez = new FezDeliveryService();
            $items = MarketplaceOrderItem::where('order_id', $order->id)->get();
            $totalWeight = 0;
            $descriptions = [];
            foreach ($items as $item) {
                $product = MarketplaceProduct::find($item->product_id);
                $totalWeight += ($product->weight ?? 1) * $item->quantity;
                $descriptions[] = $item->product_name . ' x' . $item->quantity;
            }

            $result = $fez->createOrder([
                'recipientAddress' => $order->delivery_address,
                'recipientState' => $order->delivery_state ?? 'Lagos',
                'recipientName' => $order->delivery_name,
                'recipientPhone' => $order->delivery_phone,
                'uniqueID' => $order->reference,
                'BatchID' => 'VENDLIKE_' . date('Ymd'),
                'valueOfItem' => (string) $order->total_amount,
                'weight' => max(1, (int) ceil($totalWeight)),
                'itemDescription' => implode(', ', array_slice($descriptions, 0, 3)),
            ]);

            if (isset($result['orderNos']) && is_array($result['orderNos'])) {
                $fezOrderNo = $result['orderNos'][$order->reference] ?? array_values($result['orderNos'])[0] ?? null;
                if ($fezOrderNo) {
                    $order->update([
                        'fez_order_no' => $fezOrderNo,
                        'delivery_status' => 'Pending Pick-Up',
                    ]);
                }
            } else {
                Log::error('Fez order creation failed', ['response' => $result, 'order' => $order->reference]);
            }
        } catch (\Exception $e) {
            Log::error('Fez booking error', ['error' => $e->getMessage(), 'order' => $order->reference]);
        }
    }

    /**
     * Verify Monnify payment status
     */
    private function verifyMonnifyPayment(string $transactionRef, $habukhan_key): ?array
    {
        try {
            // Get access token
            $base = base64_encode($habukhan_key->mon_app_key . ':' . $habukhan_key->mon_sk_key);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.monnify.com/api/v1/auth/login',
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $base],
            ]);
            $authResult = json_decode(curl_exec($ch), true);
            curl_close($ch);

            $accessToken = $authResult['responseBody']['accessToken'] ?? null;
            if (!$accessToken)
                return null;

            // Verify transaction
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.monnify.com/api/v2/transactions/' . urlencode($transactionRef),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
            ]);
            $response = json_decode(curl_exec($ch), true);
            curl_close($ch);

            return $response['responseBody'] ?? null;
        } catch (\Exception $e) {
            Log::error('Monnify verification error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function mapFezStatus(string $fezStatus): string
    {
        $map = [
            'Pending Pick-Up' => 'processing',
            'Picked-Up' => 'processing',
            'Dispatched' => 'shipped',
            'Delivered' => 'delivered',
            'Returned' => 'cancelled',
        ];
        return $map[$fezStatus] ?? 'processing';
    }

    private function isMarketplaceEnabled(): bool
    {
        $settings = DB::table('settings')->first();
        return (int) ($settings->marketplace_status ?? 1) === 1;
    }

    private function isAdmin($userId): bool
    {
        $user = DB::table('user')->where(['id' => $userId, 'status' => 1])->first();
        return $user && $user->type === 'ADMIN';
    }
}
