@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#16a34a; margin:0 0 5px;">✅ Order Delivered</h2>
    <p style="color:#666; margin:5px 0 0; font-size:14px;">Your package has arrived!</p>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ ucfirst($username) }}</strong>,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">Your order <strong style="font-family:monospace;">{{ $reference ?? 'N/A' }}</strong> has been successfully delivered.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Order Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-weight:bold; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $reference ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Total Paid</td><td style="padding:12px 16px; text-align:right; font-weight:bold; font-size:13px; border-bottom:1px solid #f0f0f0;">₦{{ number_format($grand_total ?? 0, 2) }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Status</td><td style="padding:12px 16px; text-align:right; border-bottom:1px solid #e5e7eb;"><span style="display:inline-block; background:#dcfce7; color:#166534; padding:4px 14px; border-radius:12px; font-size:11px; font-weight:bold;">DELIVERED</span></td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px;">Delivered On</td><td style="padding:12px 16px; text-align:right; font-size:13px;">{{ $date ?? date('d M Y, h:i A') }}</td></tr>
</table>

@if(isset($items) && is_array($items) && count($items) > 0)
<div style="margin-top:20px;">
    <h4 style="color:#333; font-size:14px; margin:0 0 10px;">📦 Items Delivered</h4>
    @foreach($items as $item)
    <p style="margin:6px 0; font-size:13px; color:#555;">• {{ $item['name'] ?? 'Product' }} x{{ $item['quantity'] ?? 1 }}</p>
    @endforeach
</div>
@endif

<div style="margin-top:25px; text-align:center; background:#f0fdf4; border:2px solid #22c55e; border-radius:12px; padding:20px;">
    <p style="color:#166534; font-size:16px; font-weight:600; margin:0;">Thank you for shopping with {{ config('app.name') }}! 🎉</p>
    <p style="color:#888; font-size:13px; margin:8px 0 0;">We hope you enjoy your purchase.</p>
</div>

<div style="margin-top:25px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px; font-size:12px; color:#92400e;">
    <strong>📌 Issue with your order?</strong> If you have any problems with your delivery, please contact our support team immediately with your order reference.
</div>
@endsection
