@extends('email.layouts.master')

@section('content')
  <div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #00466a;">{{ $title }}</h2>
  </div>
  <div style="line-height: 1.6; color: #444;">
    {!! $messages !!}
  </div>

  <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
    <p>Thank for choosing <strong>{{ $app_name }}</strong></p>
    <p style="font-size: 0.9em; margin: 0;">Regards,<br><strong>{{ $app_name }} Team</strong></p>
  </div>
@endsection