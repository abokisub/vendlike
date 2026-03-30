@extends('email.layouts.master')

@section('content')
    <div style="text-align:center; margin-bottom:25px;">
        <h2 style="color:#00466a; margin:0 0 5px;">Transaction PIN Reset</h2>
        <p style="font-size:14px; color:#666; margin:0;">Use the code below to complete your PIN reset</p>
    </div>

    <div
        style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:30px; text-align:center; margin-bottom:25px;">
        <p
            style="font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin:0 0 10px; font-weight:bold;">
            Verification Code (OTP)</p>
        <div style="font-size:36px; font-weight:800; color:#00466a; letter-spacing:8px; margin-bottom:5px;">{{ $otp }}</div>
        <p style="font-size:11px; color:#94a3b8; margin:0;">This code will expire in 10 minutes</p>
    </div>

    <p style="font-size:14px; color:#444;">Hello <strong>{{ $name ?? $username }}</strong>,</p>
    <p style="font-size:14px; color:#444; line-height:1.6;">We received a request to reset your transaction PIN. If you did
        not make this request, please secure your account immediately.</p>

    <div style="margin-top:20px; padding:15px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px;">
        <p style="margin:0; font-size:12px; color:#92400e;"><strong>Security Tip:</strong> Never share your OTP or PIN with
            anyone, including {{ config('app.name') }} staff.</p>
    </div>

    <div style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
        <p style="font-size:12px; color:#999; margin:0;">
            IP Address: {{ $ip_address ?? 'N/A' }}<br>
            Device: {{ $device ?? 'N/A' }}<br>
            Location: {{ $location ?? 'N/A' }}
        </p>
    </div>
@endsection