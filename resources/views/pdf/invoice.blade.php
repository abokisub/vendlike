<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $reference ?? 'N/A' }}</title>
    <style>
        @page { margin: 80px 30px 60px 30px; }
        header { position: fixed; top: -50px; left: 0; right: 0; height: 40px; text-align: center; font-size: 9px; color: #999; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #333; }
        .watermark { position: fixed; top: 30%; left: 15%; width: 70%; text-align: center; opacity: 0.03; z-index: -1000; transform: rotate(-40deg); font-size: 72px; font-weight: bold; color: #000; }

        .brand { font-size: 26px; font-weight: bold; color: #00A86B; }
        .brand-sub { font-size: 11px; color: #888; margin-top: 2px; }
        .invoice-title { font-size: 22px; font-weight: bold; color: #333; text-align: right; text-transform: uppercase; }
        .invoice-badge { display: inline-block; background: #dcfce7; color: #166534; padding: 4px 14px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .invoice-badge.failed { background: #fee2e2; color: #991b1b; }
        .invoice-badge.pending { background: #fef3c7; color: #92400e; }

        .divider { border-bottom: 2px solid #00A86B; margin: 15px 0; }
        .divider-light { border-bottom: 1px solid #eee; margin: 12px 0; }

        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { vertical-align: top; padding: 3px 0; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #999; letter-spacing: 0.5px; }
        .info-value { font-size: 12px; font-weight: bold; color: #222; }

        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th { background: #f8f9fa; padding: 10px 8px; text-align: left; font-size: 10px; text-transform: uppercase; color: #666; border-bottom: 2px solid #ddd; letter-spacing: 0.5px; }
        .items-table td { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; font-size: 11px; }
        .items-table .amount { text-align: right; font-weight: bold; }

        .total-section { margin-top: 15px; }
        .total-row { padding: 6px 0; }
        .total-label { color: #666; font-size: 11px; }
        .total-value { font-weight: bold; font-size: 11px; text-align: right; }
        .grand-total { font-size: 16px; color: #00A86B; font-weight: bold; }

        .card-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px; margin-top: 15px; }
        .card-box h4 { margin: 0 0 8px; color: #166534; font-size: 12px; }
        .card-detail { margin: 4px 0; font-size: 11px; }
        .card-detail strong { color: #333; }
        .card-detail .mono { font-family: 'DejaVu Sans Mono', monospace; background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; }

        .pin-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 12px; margin-top: 10px; }
        .pin-box h4 { margin: 0 0 8px; color: #92400e; font-size: 12px; }

        .delivery-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px; margin-top: 15px; }
        .delivery-box h4 { margin: 0 0 8px; color: #0369a1; font-size: 12px; }

        .note { margin-top: 20px; font-size: 10px; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="watermark">{{ strtoupper($app_name ?? config('app.name')) }}</div>
    <header>Invoice - {{ $app_name ?? config('app.name') }} | Ref: {{ $reference ?? 'N/A' }}</header>
    <footer>&copy; {{ date('Y') }} {{ $app_name ?? config('app.name') }}. All rights reserved. Computer-generated document.</footer>

    {{-- HEADER --}}
    <table style="width:100%; margin-bottom: 5px;">
        <tr>
            <td style="vertical-align:top;">
                <div class="brand">{{ $app_name ?? config('app.name') }}</div>
                <div class="brand-sub">{{ $app_url ?? config('app.url') }}</div>
            </td>
            <td style="text-align:right; vertical-align:top;">
                <div class="invoice-title">{{ $invoice_type ?? 'INVOICE' }}</div>
                <div style="margin-top:5px;">
                    <span class="invoice-badge {{ strtolower($status ?? 'success') === 'failed' ? 'failed' : (strtolower($status ?? '') === 'pending' ? 'pending' : '') }}">{{ strtoupper($status ?? 'SUCCESSFUL') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- CUSTOMER + INVOICE INFO --}}
    <table class="info-table">
        <tr>
            <td style="width:50%;">
                <div class="info-label">Customer</div>
                <div class="info-value">{{ strtoupper($customer_name ?? $username ?? 'N/A') }}</div>
                @if(isset($customer_email))<div style="font-size:10px; color:#666; margin-top:2px;">{{ $customer_email }}</div>@endif
                @if(isset($customer_phone))<div style="font-size:10px; color:#666;">{{ $customer_phone }}</div>@endif
            </td>
            <td style="text-align:right;">
                <div class="info-label">Reference</div>
                <div class="info-value" style="font-family:'DejaVu Sans Mono',monospace; font-size:11px;">{{ $reference ?? 'N/A' }}</div>
                <div class="info-label" style="margin-top:8px;">Date</div>
                <div class="info-value" style="font-size:11px;">{{ $date ?? date('d M Y, h:i A') }}</div>
            </td>
        </tr>
    </table>

    <div class="divider-light"></div>

    {{-- SERVICE-SPECIFIC CONTENT --}}
    @if(isset($service_type))

        @if($service_type === 'GIFT_CARD')
        {{-- GIFT CARD INVOICE --}}
        <table class="items-table">
            <thead><tr><th>Product</th><th>Brand</th><th>Qty</th><th>Unit Price</th><th style="text-align:right;">Subtotal</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $product_name ?? 'Gift Card' }}</td>
                    <td>{{ $brand_name ?? '-' }}</td>
                    <td>{{ $quantity ?? 1 }}</td>
                    <td>{{ $currency ?? 'USD' }} {{ number_format($unit_price ?? 0, 2) }}</td>
                    <td class="amount">{{ $currency ?? 'USD' }} {{ number_format(($unit_price ?? 0) * ($quantity ?? 1), 2) }}</td>
                </tr>
            </tbody>
        </table>
        <div class="total-section">
            <table style="width:100%;">
                <tr class="total-row"><td class="total-label">Card Value ({{ $currency ?? 'USD' }})</td><td class="total-value">{{ $currency ?? 'USD' }} {{ number_format(($unit_price ?? 0) * ($quantity ?? 1), 2) }}</td></tr>
                <tr class="total-row"><td class="total-label">Exchange Rate</td><td class="total-value">Applied</td></tr>
                <tr class="total-row"><td style="font-weight:bold; font-size:13px;">Amount Paid</td><td class="total-value grand-total">&#8358;{{ number_format($naira_amount ?? 0, 2) }}</td></tr>
            </table>
        </div>
        @if(isset($cards) && is_array($cards) && count($cards) > 0)
        <div class="card-box">
            <h4>Gift Card Details{{ count($cards) > 1 ? ' (' . count($cards) . ' cards)' : '' }}</h4>
            @foreach($cards as $idx => $card)
                @if(count($cards) > 1)<div style="font-weight:bold; font-size:10px; color:#166534; margin-top:{{ $idx > 0 ? '10' : '0' }}px;">Card {{ $idx + 1 }}</div>@endif
                @if(isset($card['cardNumber']))<div class="card-detail"><strong>Card Number:</strong> <span class="mono">{{ $card['cardNumber'] }}</span></div>@endif
                @if(isset($card['pinCode']))<div class="card-detail"><strong>PIN Code:</strong> <span class="mono">{{ $card['pinCode'] }}</span></div>@endif
                @if(isset($card['redemptionUrl']))<div class="card-detail"><strong>Redeem URL:</strong> {{ $card['redemptionUrl'] }}</div>@endif
            @endforeach
        </div>
        @endif
        @if(isset($redeem_instructions))
        <div style="margin-top:10px; font-size:10px; color:#666;"><strong>How to Redeem:</strong> {{ $redeem_instructions }}</div>
        @endif

        @elseif($service_type === 'JAMB_PIN')
        {{-- JAMB PIN INVOICE --}}
        <table class="items-table">
            <thead><tr><th>Service</th><th>Type</th><th>Profile ID</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>JAMB PIN</td>
                    <td>{{ $variation_name ?? 'UTME' }}</td>
                    <td style="font-family:'DejaVu Sans Mono',monospace;">{{ $profile_id ?? 'N/A' }}</td>
                    <td class="amount">&#8358;{{ number_format($amount ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
        @if(isset($customer_name_student))<div style="margin-top:8px; font-size:11px;"><strong>Student Name:</strong> {{ $customer_name_student }}</div>@endif
        @if(isset($purchased_code) && $purchased_code)
        <div class="pin-box">
            <h4>Your JAMB PIN</h4>
            <div style="font-family:'DejaVu Sans Mono',monospace; font-size:18px; font-weight:bold; letter-spacing:2px; text-align:center; padding:10px;">{{ $purchased_code }}</div>
            <div style="text-align:center; font-size:9px; color:#92400e;">Keep this PIN safe. Do not share with anyone.</div>
        </div>
        @endif

        @elseif($service_type === 'RECHARGE_CARD')
        {{-- RECHARGE CARD INVOICE --}}
        <table class="items-table">
            <thead><tr><th>Network</th><th>Card</th><th>Qty</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $network ?? 'N/A' }}</td>
                    <td>{{ $card_name ?? 'Recharge Card' }}</td>
                    <td>{{ $quantity ?? 1 }}</td>
                    <td class="amount">&#8358;{{ number_format($amount ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
        @if(isset($load_pin))<div style="margin-top:8px; font-size:11px;"><strong>How to Load:</strong> Dial <span style="font-family:'DejaVu Sans Mono',monospace;">{{ $load_pin }}</span></div>@endif
        @if(isset($check_balance))<div style="font-size:11px;"><strong>Check Balance:</strong> Dial <span style="font-family:'DejaVu Sans Mono',monospace;">{{ $check_balance }}</span></div>@endif
        @if(isset($pins) && is_array($pins) && count($pins) > 0)
        <div class="pin-box">
            <h4>Recharge Card PINs ({{ count($pins) }})</h4>
            <table style="width:100%; border-collapse:collapse; margin-top:5px;">
                <tr style="border-bottom:1px solid #fde68a;"><th style="text-align:left; padding:4px; font-size:9px; color:#92400e;">#</th><th style="text-align:left; padding:4px; font-size:9px; color:#92400e;">PIN</th><th style="text-align:left; padding:4px; font-size:9px; color:#92400e;">Serial Number</th></tr>
                @foreach($pins as $idx => $pin_data)
                <tr><td style="padding:4px; font-size:10px;">{{ $idx + 1 }}</td><td style="padding:4px; font-family:'DejaVu Sans Mono',monospace; font-size:11px; font-weight:bold;">{{ $pin_data['pin'] ?? 'N/A' }}</td><td style="padding:4px; font-family:'DejaVu Sans Mono',monospace; font-size:10px;">{{ $pin_data['serial'] ?? '-' }}</td></tr>
                @endforeach
            </table>
        </div>
        @endif

        @elseif($service_type === 'EXAM_PIN')
        {{-- EXAM PIN INVOICE --}}
        <table class="items-table">
            <thead><tr><th>Service</th><th>Exam</th><th>Qty</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>Exam PIN</td>
                    <td>{{ $exam_name ?? 'N/A' }}</td>
                    <td>{{ $quantity ?? 1 }}</td>
                    <td class="amount">&#8358;{{ number_format($amount ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
        @if(isset($purchased_code) && $purchased_code)
        <div class="pin-box">
            <h4>Your {{ $exam_name ?? 'Exam' }} PIN</h4>
            <div style="font-family:'DejaVu Sans Mono',monospace; font-size:16px; font-weight:bold; letter-spacing:2px; text-align:center; padding:10px; word-break:break-all;">{{ $purchased_code }}</div>
            <div style="text-align:center; font-size:9px; color:#92400e;">Keep this PIN safe. Do not share with anyone.</div>
        </div>
        @endif

        @elseif($service_type === 'MARKETPLACE')
        {{-- MARKETPLACE ORDER INVOICE --}}
        @if(isset($items) && is_array($items) && count($items) > 0)
        <table class="items-table">
            <thead><tr><th>Item</th><th>Qty</th>@if(isset($items[0]['size']))<th>Size</th>@endif @if(isset($items[0]['color']))<th>Color</th>@endif<th style="text-align:right;">Subtotal</th></tr></thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $item['name'] ?? 'Product' }}</td>
                    <td>{{ $item['quantity'] ?? 1 }}</td>
                    @if(isset($items[0]['size']))<td>{{ $item['size'] ?? '-' }}</td>@endif
                    @if(isset($items[0]['color']))<td>{{ $item['color'] ?? '-' }}</td>@endif
                    <td class="amount">&#8358;{{ number_format($item['subtotal'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        <div class="total-section">
            <table style="width:100%;">
                <tr class="total-row"><td class="total-label">Items Total</td><td class="total-value">&#8358;{{ number_format($total_amount ?? 0, 2) }}</td></tr>
                <tr class="total-row"><td class="total-label">Delivery Fee</td><td class="total-value">&#8358;{{ number_format($delivery_fee ?? 0, 2) }}</td></tr>
                <tr class="total-row"><td style="font-weight:bold; font-size:13px;">Grand Total</td><td class="total-value grand-total">&#8358;{{ number_format($grand_total ?? 0, 2) }}</td></tr>
            </table>
        </div>
        @if(isset($delivery_name) || isset($delivery_address))
        <div class="delivery-box">
            <h4>Delivery Information</h4>
            @if(isset($delivery_name))<div style="font-size:11px;"><strong>Name:</strong> {{ $delivery_name }}</div>@endif
            @if(isset($delivery_phone))<div style="font-size:11px;"><strong>Phone:</strong> {{ $delivery_phone }}</div>@endif
            @if(isset($delivery_address))<div style="font-size:11px;"><strong>Address:</strong> {{ $delivery_address }}</div>@endif
            @if(isset($delivery_state))<div style="font-size:11px;"><strong>State:</strong> {{ $delivery_state }}</div>@endif
            @if(isset($delivery_eta))<div style="font-size:11px;"><strong>ETA:</strong> {{ $delivery_eta }}</div>@endif
            @if(isset($tracking_number))<div style="font-size:11px;"><strong>Tracking:</strong> <span style="font-family:'DejaVu Sans Mono',monospace;">{{ $tracking_number }}</span></div>@endif
        </div>
        @endif

    @endif {{-- end service_type switch --}}

    @endif {{-- end isset service_type --}}

    {{-- WALLET BALANCE --}}
    @if(isset($newbal))
    <div class="divider-light"></div>
    <table style="width:100%;"><tr><td class="total-label">New Wallet Balance</td><td class="total-value">&#8358;{{ number_format($newbal, 2) }}</td></tr></table>
    @endif

    <div class="note">
        <p><strong>Note:</strong> This is a computer-generated invoice. All amounts are in Nigerian Naira (&#8358;) unless otherwise stated. If you notice any discrepancy, please contact our support team immediately.</p>
        <p>Thank you for choosing {{ $app_name ?? config('app.name') }}.</p>
    </div>
</body>
</html>
