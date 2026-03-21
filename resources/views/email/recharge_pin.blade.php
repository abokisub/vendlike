@extends('email.layouts.master')

@section('content')
<div style="text-align:center; margin-bottom:25px;">
    <h2 style="color:#28a745; margin:0 0 5px;">Recharge Card Purchase Successful</h2>
    <p style="font-size:28px; font-weight:bold; color:#222; margin:0;">₦{{ number_format($amount ?? 0, 2) }}</p>
    <span style="display:inline-block; background:#dcfce7; color:#166534; padding:4px 14px; border-radius:15px; font-size:11px; font-weight:bold; margin-top:8px;">SUCCESSFUL</span>
</div>

<p style="font-size:14px; color:#444;">Hello <strong>{{ ucfirst($username) }}</strong>,</p>
<p style="font-size:14px; color:#444; line-height:1.6;">Your recharge card PINs have been generated successfully.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Network</td><td style="padding:12px 16px; text-align:right; font-weight:600; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $network ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Card Name</td><td style="padding:12px 16px; text-align:right; font-size:13px; border-bottom:1px solid #f0f0f0;">{{ $card_name ?? 'N/A' }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Quantity</td><td style="padding:12px 16px; text-align:right; font-weight:600; font-size:13px; border-bottom:1px solid #e5e7eb;">{{ $quantity ?? 1 }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #f0f0f0;">Amount Paid</td><td style="padding:12px 16px; text-align:right; font-weight:bold; font-size:14px; color:#16a34a; border-bottom:1px solid #f0f0f0;">₦{{ number_format($amount ?? 0, 2) }}</td></tr>
    <tr style="background:#f9fafb;"><td style="padding:12px 16px; color:#666; font-size:13px; border-bottom:1px solid #e5e7eb;">Reference</td><td style="padding:12px 16px; text-align:right; font-family:monospace; font-size:12px; border-bottom:1px solid #e5e7eb;">{{ $transid ?? 'N/A' }}</td></tr>
    <tr><td style="padding:12px 16px; color:#666; font-size:13px;">Date</td><td style="padding:12px 16px; text-align:right; font-size:13px;">{{ $date ?? date('d M Y, h:i A') }}</td></tr>
</table>

@if((isset($load_pin) && $load_pin) || (isset($check_balance) && $check_balance))
<div style="margin-top:15px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:14px;">
    @if(isset($load_pin) && $load_pin)<p style="margin:0 0 4px; font-size:13px; color:#0369a1;"><strong>How to Load:</strong> Dial <span style="font-family:monospace; font-weight:bold;">{{ $load_pin }}</span></p>@endif
    @if(isset($check_balance) && $check_balance)<p style="margin:0; font-size:13px; color:#0369a1;"><strong>Check Balance:</strong> Dial <span style="font-family:monospace; font-weight:bold;">{{ $check_balance }}</span></p>@endif
</div>
@endif

@if(isset($pins) && is_array($pins) && count($pins) > 0)
<div style="margin-top:25px;">
    <h3 style="color:#166534; font-size:15px; margin:0 0 12px; text-align:center;">🔑 Your Recharge Card PINs</h3>
    @foreach($pins as $index => $pin_data)
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px; margin-bottom:8px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:30px; vertical-align:top;"><span style="display:inline-block; background:#22c55e; color:#fff; width:22px; height:22px; border-radius:50%; text-align:center; line-height:22px; font-size:11px; font-weight:bold;">{{ $index + 1 }}</span></td>
                <td>
                    <p style="margin:0 0 4px; font-size:13px;"><strong>PIN:</strong> <span style="font-family:'Courier New',monospace; background:#fff; padding:3px 8px; border-radius:4px; font-weight:bold; letter-spacing:1px;">{{ $pin_data['pin'] ?? 'N/A' }}</span></p>
                    @if(isset($pin_data['serial']) && $pin_data['serial'])<p style="margin:0; font-size:12px; color:#666;"><strong>Serial:</strong> <span style="font-family:monospace;">{{ $pin_data['serial'] }}</span></p>@endif
                </td>
            </tr>
        </table>
    </div>
    @endforeach
</div>
@endif

@if(isset($newbal))
<div style="margin-top:20px; text-align:center; border-top:1px dashed #ddd; padding-top:15px;">
    <p style="color:#888; font-size:11px; margin:0 0 4px;">New Wallet Balance</p>
    <strong style="font-size:16px; color:#222;">₦{{ number_format($newbal, 2) }}</strong>
</div>
@endif

<div style="margin-top:25px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:14px; font-size:12px; color:#92400e;">
    <strong>⚠️ Important:</strong> Keep your recharge card PINs confidential. {{ config('app.name') }} will never ask for your PINs via email or phone.
</div>

<p style="margin-top:20px; font-size:12px; color:#999; text-align:center;">A PDF invoice is attached to this email for your records.</p>
@endsection
