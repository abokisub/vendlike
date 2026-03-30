<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>New Gift Card Sale - {{ $app_name }}</title>
</head>

<body
    style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0;">
    <div
        style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e1e8ed; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

        <!-- Header -->
        <div style="background-color: #00466a; color: #ffffff; padding: 25px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">🎁 New Gift Card Sale</h1>
            <p style="margin: 5px 0 0; opacity: 0.9;">Pending Review</p>
        </div>

        <!-- Body -->
        <div style="padding: 30px;">
            <p style="font-size: 16px; color: #333; margin-top: 0;">Hello Admin,</p>
            <p style="font-size: 15px; color: #555; line-height: 1.6;">
                A user has submitted a gift card for sale. Please review the details below and process it in the admin
                panel.
            </p>

            <!-- Details Table -->
            <div
                style="margin: 25px 0; background-color: #f9fafb; border-radius: 6px; padding: 20px; border: 1px solid #edf2f7;">
                <h3
                    style="margin-top: 0; margin-bottom: 15px; color: #2d3748; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    Sale Details
                </h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px; width: 40%;">User:</td>
                        <td style="padding: 8px 0; color: #2d3748; font-size: 14px; font-weight: 600;">{{ $username }}
                            ({{ $name }})</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px;">Card Type:</td>
                        <td style="padding: 8px 0; color: #2d3748; font-size: 14px; font-weight: 600;">{{ $card_type }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px;">Card Amount:</td>
                        <td style="padding: 8px 0; color: #2d3748; font-size: 14px; font-weight: 600;">
                            ${{ number_format($card_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px;">Expected Payout:</td>
                        <td style="padding: 8px 0; color: #38a169; font-size: 15px; font-weight: 700;">
                            ₦{{ number_format($expected_naira, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px;">Method:</td>
                        <td style="padding: 8px 0; color: #2d3748; font-size: 14px; font-weight: 600;">
                            {{ ucfirst($redemption_method) }}</td>
                    </tr>
                    @if($card_code)
                        <tr>
                            <td style="padding: 8px 0; color: #718096; font-size: 14px;">Card Code:</td>
                            <td
                                style="padding: 8px 0; font-family: 'Courier New', Courier, monospace; background: #edf2f7; padding: 6px 10px; border-radius: 4px; color: #2d3748; font-size: 13px;">
                                {{ $card_code }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px;">Reference:</td>
                        <td style="padding: 8px 0; font-family: monospace; font-size: 13px; color: #4a5568;">
                            {{ $transid }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #718096; font-size: 14px;">Date:</td>
                        <td style="padding: 8px 0; color: #2d3748; font-size: 14px;">{{ $date }}</td>
                    </tr>
                </table>
            </div>

            <!-- Image Section -->
            @if(!empty($image_urls))
                <div style="margin: 25px 0;">
                    <h3 style="color: #2d3748; font-size: 16px; margin-bottom: 12px;">🖼️ Uploaded Images</h3>
                    <div style="text-align: center;">
                        @foreach($image_urls as $url)
                            <div
                                style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background-color: #fdfdfd;">
                                <a href="{{ $url }}" target="_blank" style="text-decoration: none;">
                                    <img src="{{ $url }}" alt="Gift Card Image"
                                        style="max-width: 100%; display: block; margin: 0 auto; max-height: 400px; padding: 10px;">
                                    <div
                                        style="background-color: #f7fafc; padding: 8px; color: #4a5568; font-size: 12px; border-top: 1px solid #e2e8f0;">
                                        Click to view full image
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Action Button -->
            <div style="text-align: center; margin-top: 35px;">
                <a href="{{ config('app.url') }}/admin/gift-cards/redemptions"
                    style="display: inline-block; background-color: #00466a; color: #ffffff; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    Go to Admin Panel
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding: 20px; border-top: 1px solid #e2e8f0; text-align: center; background-color: #f7fafc;">
            <p style="margin: 0; color: #a0aec0; font-size: 12px;">
                &copy; {{ date('Y') }} {{ $app_name }}. All rights reserved.
            </p>
            <p style="margin: 5px 0 0; color: #a0aec0; font-size: 11px;">
                Support: {{ $sender_mail }}
            </p>
        </div>
    </div>
</body>

</html>