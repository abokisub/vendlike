@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#28a745; margin:0 0 5px;">JAMB PIN Purchase Successful</h2>
    <p style="font-size:28px; font-weight:bold; color:#222; margin:0;">₦{{ number_format($amount ?? 0, 2) }}</p>
    <span style="display:inline-block; background:#dcfce7; color:#166534; padding:4px 14px; border-radius:15px; font-size:11px; font-weight:bold; margin-top:8px;">SUCCESSFUL</span>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ ucfirst($username) }}</strong>,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">Your JAMB PIN has been purchased successfully. Find your details below.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">JAMB Type</td><td style="padding:12px 16px; text-align:right; font-weight:600; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $variation_name ?? 'UTME' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Profile ID</td><td style="padding:12px 16px; text-align:right; font-size:13px; font-family:monospace; border-bottom:1px solid #f0f0f0;">{{ $profile_id ?? 'N/A' }}</td></tr>
    @if(isset($customer_name) && $customer_name)
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Student Name</td><td style="padding:12px 16px; text-align:right; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $customer_name }}</td></tr>
    @endif
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Phone</td><td style="padding:12px 16px; text-align:right; font-size:13px; border-bottom:1px solid #f0f0f0;">{{ $phone ?? 'N/A' }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Amount Paid</td><td style="padding:12px 16px; text-align:right; font-weight:bold; font-size:14px; color:#16a34a; border-bottom:1px solid #e5e7eb;">₦{{ number_format($amount ?? 0, 2) }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-size:12px; border-bottom:1px solid #f0f0f0;">{{ $transid ?? 'N/A' }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px;">Date</td><td style="padding:12px 16px; text-align:right; font-size:13px;">{{ $date ?? date('d M Y, h:i A') }}</td></tr>
</table>

@if(isset($purchased_code) && $purchased_code)
<div style="margin-top:25px; text-align:center; background:#f0fdf4; border:2px solid #22c55e; border-radius:12px; padding:25px;">
    <p style="color:#166534; font-size:13px; margin:0 0 10px; font-weight:600;">Your JAMB PIN</p>
    <div style="background:#fff; border:1px dashed #22c55e; border-radius:8px; padding:15px; display:inline-block;">
        <p style="font-family:'Courier New',monospace; font-size:22px; font-weight:bold; color:#111; letter-spacing:2px; margin:0;">{{ $purchased_code }}</p>
    </div>
    <p style="color:#888; font-size:10px; margin:12px 0 0;">Keep this PIN safe. Do not share it with anyone.</p>
</div>
@endif

@if(isset($newbal))
<div style="margin-top:20px; text-align:center; border-top:1px dashed #ddd; padding-top:15px;">
    <p style="color:#888; font-size:11px; margin:0 0 4px;">New Wallet Balance</p>
    <strong style="font-size:16px; color:#222;">₦{{ number_format($newbal, 2) }}</strong>
</div>
@endif

<div style="margin-top:25px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px; font-size:12px; color:#92400e;">
    <strong>⚠️ Important:</strong> Keep your JAMB PIN confidential. {{ config('app.name') }} will never ask for your PIN via email or phone.
</div>

<p style="margin-top:20px; font-size:12px; color:#999; text-align:center;">A PDF invoice is attached to this email for your records.</p>
@endsection
