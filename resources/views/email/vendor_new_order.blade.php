@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#2563eb; margin:0 0 5px;">📦 New Order for Pickup</h2>
    <p style="font-size:20px; font-weight:bold; color:#222; margin:0;">{{ $reference ?? 'N/A' }}</p>
    <span style="display:inline-block; background:#dbeafe; color:#1e40af; padding:4px 14px; border-radius:15px; font-size:11px; font-weight:bold; margin-top:8px;">PREPARE FOR PICKUP</span>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ $vendor_name ?? 'Vendor' }}</strong>@if(isset($business_name)) ({{ $business_name }})@endif,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">You have a new order that needs to be prepared for pickup by FEZ Delivery.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Order Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:bold; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $reference ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px;">Order Date</td><td style="padding:12px 16px; text-align:right; font-size:13px;">{{ $date ?? date('d M Y, h:i A') }}</td></tr>
</table>

@if(isset($items) && is_array($items) && count($items) > 0)
<div style="margin-top:25px;">
    <h3 style="color:#333; font-size:15px; margin:0 0 12px;">📦 Items to Prepare</h3>
    @foreach($items as $item)
    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:12px 16px; margin-bottom:8px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <p style="margin:0; font-weight:600; font-size:14px; color:#222;">{{ $item['name'] ?? 'Product' }}</p>
                    <p style="margin:4px 0 0; color:#888; font-size:12px;">Qty: {{ $item['quantity'] ?? 1 }}@if(isset($item['size']) && $item['size']) &middot; Size: {{ $item['size'] }}@endif @if(isset($item['color']) && $item['color']) &middot; Color: {{ $item['color'] }}@endif</p>
                </td>
            </tr>
        </table>
    </div>
    @endforeach
</div>
@endif

<div style="margin-top:20px; background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:16px;">
    <h4 style="color:#92400e; margin:0 0 10px; font-size:14px;">📍 Your Pickup Location</h4>
    @if(isset($pickup_address))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Address:</strong> {{ $pickup_address }}</p>@endif
    @if(isset($pickup_phone))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Contact Phone:</strong> {{ $pickup_phone }}</p>@endif
</div>

@if(isset($delivery_name) || isset($delivery_address))
<div style="margin-top:12px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px;">
    <h4 style="color:#0369a1; margin:0 0 10px; font-size:14px;">🚚 Customer Delivery Details</h4>
    @if(isset($delivery_name))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Name:</strong> {{ $delivery_name }}</p>@endif
    @if(isset($delivery_address))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Address:</strong> {{ $delivery_address }}</p>@endif
    @if(isset($delivery_state))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>State:</strong> {{ $delivery_state }}</p>@endif
</div>
@endif

<div style="margin-top:25px; background:#dcfce7; border:1px solid #86efac; border-radius:8px; padding:14px; font-size:13px; color:#166534;">
    <strong>✅ Action Required:</strong> {{ $message ?? 'Please prepare these items for pickup. FEZ Delivery will collect them from your location soon.' }}
</div>

<p style="margin-top:20px; font-size:12px; color:#999; text-align:center;">You will receive another email when FEZ picks up the items.</p>
@endsection
