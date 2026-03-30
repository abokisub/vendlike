@extends('email.layouts.master')

@section('content')
    <div style="background-color: #00466a; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">Admin Alert</h2>
    </div>
    <div style="padding: 30px; border: 1px solid #eee; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Hi Admin,</p>
        <p>{!! nl2br(e($body)) !!}</p>
        <div style="text-align: center; margin-top: 25px;">
            <a href="{{ config('app.app_url') }}"
                style="display: inline-block; padding: 12px 25px; background-color: #00466a; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">Login
                to Admin Panel</a>
        </div>
    </div>
@endsection