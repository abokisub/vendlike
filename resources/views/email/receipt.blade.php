@extends('email.layouts.master')

@section('content')
    <div style="text-align: center; margin-bottom: 30px;">
        <h2>{{ $title }}</h2>
    </div>
    <div style="margin-bottom: 30px;">
        <p>Hello {{ $name }},</p>
        <p>Thank you for your purchase on <strong>{{ $app_name }}</strong>.</p>
        <p>Please find attached the PDF receipt for your transaction.</p>

        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 10px;">
            <p><strong>Transaction ID:</strong> #{{ $transid }}</p>
            <p><strong>Date:</strong> {{ $date }}</p>
            <p><strong>Status:</strong> Successful</p>
        </div>

        <p style="margin-top: 20px;">If you have any questions regarding this purchase, please contact our support team.</p>
    </div>
@endsection