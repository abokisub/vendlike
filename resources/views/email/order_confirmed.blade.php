@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#28a745; margin:0 0 5px;">🛒 Order Confirmed</h2>
    <p style="font-size:28px; font-weight:bold; color:#222; margin:0;">₦{{ number_format($grand_total ?? 0, 2) }}</p>
    <span style="display:inline-block; background:#dcfce7; color:#166534; padding:4px 14px; border-radius:15px; font-size:11px; font-weight:bold; margin-top:8px;">PAYMENT CONFIRMED</span>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ ucfirst($username) }}</strong>,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">Your order has been confirmed and is now being processed.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Order Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:bold; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $reference ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Items Total</td><td style="padding:12px 16px; text-align:right; font-size:13px; border-bottom:1px solid #f0f0f0;">₦{{ number_format($total_amount ?? 0, 2) }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Delivery Fee</td><td style="padding:12px 16px; text-align:right; font-size:13px; border-bottom:1px solid #e5e7eb;">₦{{ number_format($delivery_fee ?? 0, 2) }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; font-weight:bold; border-bottom:1px solid #f0f0f0;">Grand Total</td><td style="padding:12px 16px; text-align:right; font-weight:bold; font-size:16px; color:#16a34a; border-bottom:1px solid #f0f0f0;">₦{{ number_format($grand_total ?? 0, 2) }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px;">Date</td><td style="padding:12px 16px; text-align:right; font-size:13px;">{{ $date ?? date('d M Y, h:i A') }}</td></tr>
</table>

@if(isset($items) && is_array($items) && count($items) > 0)
<div style="margin-top:25px;">
    <h3 style="color:#333; font-size:15px; margin:0 0 12px;">📦 Items Ordered</h3>
    @foreach($items as $item)
    <div style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:12px 16px; margin-bottom:8px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <p style="margin:0; font-weight:600; font-size:14px; color:#222;">{{ $item['name'] ?? 'Product' }}</p>
                    <p style="margin:4px 0 0; color:#888; font-size:12px;">Qty: {{ $item['quantity'] ?? 1 }}@if(isset($item['size']) && $item['size']) &middot; Size: {{ $item['size'] }}@endif @if(isset($item['color']) && $item['color']) &middot; Color: {{ $item['color'] }}@endif</p>
                </td>
                <td style="text-align:right; vertical-align:middle;"><strong style="font-size:14px; color:#222;">₦{{ number_format($item['subtotal'] ?? 0, 2) }}</strong></td>
            </tr>
        </table>
    </div>
    @endforeach
</div>
@endif

@if(isset($delivery_name) || isset($delivery_address))
<div style="margin-top:20px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px;">
    <h4 style="color:#0369a1; margin:0 0 10px; font-size:14px;">🚚 Delivery Details</h4>
    @if(isset($delivery_name))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Name:</strong> {{ $delivery_name }}</p>@endif
    @if(isset($delivery_phone))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Phone:</strong> {{ $delivery_phone }}</p>@endif
    @if(isset($delivery_address))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Address:</strong> {{ $delivery_address }}</p>@endif
    @if(isset($delivery_state))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>State:</strong> {{ $delivery_state }}</p>@endif
    @if(isset($delivery_eta))<p style="margin:4px 0; font-size:13px; color:#444;"><strong>Estimated Delivery:</strong> {{ $delivery_eta }}</p>@endif
</div>
@endif

<div style="margin-top:25px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px; font-size:12px; color:#92400e;">
    <strong>📌 Note:</strong> You will receive another email when your order is shipped. If you have any questions, please contact our support team.
</div>

<p style="margin-top:20px; font-size:12px; color:#999; text-align:center;">A PDF invoice is attached to this email for your records.</p>
@endsection
