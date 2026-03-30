<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 80%;
            margin: 20px auto;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .header {
            background-color: #00466a;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 30px;
        }

        .footer {
            background-color: #f8f8f8;
            color: #888;
            padding: 15px;
            text-align: center;
            font-size: 12px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #00466a;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>{{ $app_name }} Admin Alert</h1>
        </div>
        <div class="content">
            <p>Hi Admin,</p>
            <p>{!! nl2br(e($body)) !!}</p>
            <p>Please log in to the admin dashboard to process this request.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $app_name }}. All rights reserved.</p>
            <p>Sent via {{ $app_name }} Backend</p>
        </div>
    </div>
</body>

</html>