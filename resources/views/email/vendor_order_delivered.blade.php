@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#16a34a; margin:0 0 5px;">✅ Order Delivered</h2>
    <p style="font-size:20px; font-weight:bold; color:#222; margin:0;">{{ $reference ?? 'N/A' }}</p>
    <span style="display:inline-block; background:#dcfce7; color:#166534; padding:4px 14px; border-radius:15px; font-size:11px; font-weight:bold; margin-top:8px;">COMPLETED</span>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ $vendor_name ?? 'Vendor' }}</strong>@if(isset($business_name)) ({{ $business_name }})@endif,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">Great news! Your items have been successfully delivered to the customer.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Order Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:bold; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $reference ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px;">Delivery Date</td><td style="padding:12px 16px; text-align:right; font-size:13px;">{{ $date ?? date('d M Y, h:i A') }}</td></tr>
</table>

@if(isset($items) && is_array($items) && count($items) > 0)
<div style="margin-top:25px;">
    <h3 style="color:#333; font-size:15px; margin:0 0 12px;">📦 Items Delivered</h3>
    @foreach($items as $item)
    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:12px 16px; margin-bottom:8px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <p style="margin:0; font-weight:600; font-size:14px; color:#222;">{{ $item['name'] ?? 'Product' }}</p>
                    <p style="margin:4px 0 0; color:#888; font-size:12px;">Qty: {{ $item['quantity'] ?? 1 }}</p>
                </td>
            </tr>
        </table>
    </div>
    @endforeach
</div>
@endif

@if(isset($delivery_name) || isset($delivery_state))
<div style="margin-top:20px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px;">
    <h4 style="color:#0369a1; margin:0 0 10px; font-size:14px;">🚚 Delivered To</h4>
    @if(isset($delivery_name))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Customer:</strong> {{ $delivery_name }}</p>@endif
    @if(isset($delivery_state))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Location:</strong> {{ $delivery_state }}</p>@endif
</div>
@endif

<div style="margin-top:25px; background:#dcfce7; border:1px solid #86efac; border-radius:8px; padding:14px; font-size:13px; color:#166534;">
    <strong>🎉 Success!</strong> {{ $message ?? 'Thank you for your service! The customer has received their items.' }}
</div>

<p style="margin-top:20px; font-size:12px; color:#999; text-align:center;">This order is now complete. Thank you for being a valued vendor on Vendlike!</p>
@endsection
