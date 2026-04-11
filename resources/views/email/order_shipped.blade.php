@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#0369a1; margin:0 0 5px;">🚚 Your Order Has Been Shipped</h2>
    <p style="color:#666; margin:5px 0 0; font-size:14px;">Your package is on its way!</p>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ ucfirst($username) }}</strong>,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">Great news! Your order <strong style="font-family:monospace;">{{ $reference ?? 'N/A' }}</strong> has been shipped.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Order Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:bold; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $reference ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Total Paid</td><td style="padding:12px 16px; text-align:right; font-weight:bold; font-size:13px; border-bottom:1px solid #f0f0f0;">₦{{ number_format($grand_total ?? 0, 2) }}</td></tr>
    @if(isset($tracking_number) && $tracking_number)
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Tracking Number</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:bold; color:#0369a1; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $tracking_number }}</td></tr>
    @endif
    <tr><td style="padding:12px 16px; color:#666; font-size:13px;">Status</td><td style="padding:12px 16px; text-align:right;"><span style="display:inline-block; background:#dbeafe; color:#1e40af; padding:4px 14px; border-radius:12px; font-size:11px; font-weight:bold;">SHIPPED</span></td></tr>
</table>

@if(isset($items) && is_array($items) && count($items) > 0)
<div style="margin-top:20px;">
    <h4 style="color:#333; font-size:14px; margin:0 0 10px;">📦 Items in this shipment</h4>
    @foreach($items as $item)
    <p style="margin:6px 0; font-size:13px; color:#555;">• {{ $item['name'] ?? 'Product' }} x{{ $item['quantity'] ?? 1 }}</p>
    @endforeach
</div>
@endif

@if(isset($pickup_name) || isset($pickup_address))
<div style="margin-top:20px; background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:16px;">
    <h4 style="color:#92400e; margin:0 0 8px; font-size:14px;">📍 Picked Up From</h4>
    @if(isset($pickup_name))<p style="margin:4px 0; font-size:13px; color:#444;">{{ $pickup_name }}</p>@endif
    @if(isset($pickup_address))<p style="margin:4px 0; font-size:13px; color:#444;">{{ $pickup_address }}</p>@endif
    @if(isset($pickup_phone))<p style="margin:4px 0; font-size:13px; color:#444;">📞 {{ $pickup_phone }}</p>@endif
</div>
@endif

@if(isset($delivery_address))
<div style="margin-top:12px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px;">
    <h4 style="color:#0369a1; margin:0 0 8px; font-size:14px;">🚚 Delivering To</h4>
    @if(isset($delivery_name))<p style="margin:4px 0; font-size:13px; color:#444;">{{ $delivery_name }}</p>@endif
    <p style="margin:4px 0; font-size:13px; color:#444;">{{ $delivery_address }}</p>
    @if(isset($delivery_state))<p style="margin:4px 0; font-size:13px; color:#444;">{{ $delivery_state }}</p>@endif
    @if(isset($delivery_phone))<p style="margin:4px 0; font-size:13px; color:#444;">📞 {{ $delivery_phone }}</p>@endif
</div>
@endif

<div style="margin-top:25px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px; font-size:12px; color:#92400e;">
    <strong>📌 Note:</strong> You will receive a final email when your order is delivered. Please ensure someone is available at the delivery address.
</div>
@endsection
