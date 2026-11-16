<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $turf->name }} - QR Poster ({{ strtoupper($paperSize) }})</title>
    <style>
        @page {
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #ffffff;
            width: 100%;
            height: 100%;
        }
        @php
            // Scale typography, spacing, and QR dimensions cleanly across A3, A4, and A5
            $dims = match ($paperSize) {
                'a3' => [
                    'pad' => '48px 54px',
                    'brand_logo' => '64px',
                    'brand_title' => '34px',
                    'badge_font' => '15px',
                    'headline' => '46px',
                    'subhead' => '20px',
                    'qr_box' => '490px',
                    'qr_img' => '440px',
                    'turf_name' => '36px',
                    'address_font' => '18px',
                    'steps_font' => '16px',
                    'footer_font' => '15px',
                ],
                'a5' => [
                    'pad' => '20px 24px',
                    'brand_logo' => '34px',
                    'brand_title' => '18px',
                    'badge_font' => '9px',
                    'headline' => '22px',
                    'subhead' => '11px',
                    'qr_box' => '220px',
                    'qr_img' => '196px',
                    'turf_name' => '18px',
                    'address_font' => '10px',
                    'steps_font' => '9.5px',
                    'footer_font' => '9px',
                ],
                default => [ // A4
                    'pad' => '34px 40px',
                    'brand_logo' => '48px',
                    'brand_title' => '25px',
                    'badge_font' => '12px',
                    'headline' => '34px',
                    'subhead' => '15px',
                    'qr_box' => '340px',
                    'qr_img' => '304px',
                    'turf_name' => '26px',
                    'address_font' => '13px',
                    'steps_font' => '12px',
                    'footer_font' => '11px',
                ],
            };
        @endphp
        .poster-wrapper {
            padding: {{ $dims['pad'] }};
            text-align: center;
        }
        .brand-bar {
            margin-bottom: 14px;
        }
        .brand-logo {
            width: {{ $dims['brand_logo'] }};
            height: {{ $dims['brand_logo'] }};
            border-radius: 12px;
            background: #ffffff;
            padding: 4px;
            vertical-align: middle;
        }
        .brand-title {
            font-size: {{ $dims['brand_title'] }};
            font-weight: 900;
            color: #ffffff;
            vertical-align: middle;
            margin-left: 10px;
            letter-spacing: -0.5px;
        }
        .pill-badge {
            display: inline-block;
            background-color: #10b981;
            color: #052e16;
            font-size: {{ $dims['badge_font'] }};
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 6px 18px;
            border-radius: 999px;
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .headline {
            font-size: {{ $dims['headline'] }};
            font-weight: 900;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: 6px;
        }
        .subhead {
            font-size: {{ $dims['subhead'] }};
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .qr-card {
            display: inline-block;
            width: {{ $dims['qr_box'] }};
            background-color: #ffffff;
            border-radius: 28px;
            padding: 18px;
            border: 5px solid #10b981;
            margin: 0 auto 18px auto;
        }
        .qr-card img {
            width: {{ $dims['qr_img'] }};
            height: {{ $dims['qr_img'] }};
            display: block;
            margin: 0 auto;
        }
        .qr-code-tag {
            margin-top: 8px;
            font-size: {{ $dims['badge_font'] }};
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .venue-box {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 14px 20px;
            margin-bottom: 14px;
        }
        .turf-name {
            font-size: {{ $dims['turf_name'] }};
            font-weight: 900;
            color: #34d399;
            margin-bottom: 4px;
        }
        .venue-address {
            font-size: {{ $dims['address_font'] }};
            color: #e2e8f0;
            font-weight: 600;
        }
        .steps-row {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 14px;
        }
        .steps-row td {
            width: 33.33%;
            padding: 8px;
            background-color: #111c33;
            border: 1px solid #1e293b;
            border-radius: 12px;
            font-size: {{ $dims['steps_font'] }};
            color: #cbd5e1;
            font-weight: 700;
        }
        .steps-row strong {
            color: #10b981;
            display: block;
            margin-bottom: 2px;
        }
        .footer-bar {
            font-size: {{ $dims['footer_font'] }};
            color: #94a3b8;
            font-weight: 700;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="poster-wrapper">
        <div class="brand-bar">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="brand-logo" alt="Logo" />
            @endif
            <span class="brand-title">{{ config('app.name', 'TurfBooking') }}</span>
        </div>

        <div>
            <span class="pill-badge">OFFICIAL VENUE BOOKING QR</span>
        </div>

        <div class="headline">SCAN TO BOOK THIS TURF</div>
        <div class="subhead">Check live slot availability &amp; book your match in 30 seconds</div>

        <div class="qr-card">
            <img src="{{ $qrDataUri }}" alt="Turf QR Code" />
            <div class="qr-code-tag">{{ $qrUrl }}</div>
        </div>

        <div class="venue-box">
            <div class="turf-name">{{ $turf->name }} ({{ $turf->type }})</div>
            <div class="venue-address">
                {{ $turf->location?->name }}{{ $turf->location?->address ? ' • ' . $turf->location->address : '' }}
            </div>
        </div>

        <table class="steps-row" cellspacing="8">
            <tr>
                <td>
                    <strong>1. SCAN QR CODE</strong>
                    Open your phone camera &amp; point at the QR
                </td>
                <td>
                    <strong>2. PICK TIME SLOT</strong>
                    Opens directly on {{ $turf->name }} in the app
                </td>
                <td>
                    <strong>3. PLAY &amp; ENJOY</strong>
                    Get instant booking confirmation &amp; invoice
                </td>
            </tr>
        </table>

        <div class="footer-bar">
            Available on Google Play Store (Android) &amp; Apple App Store (iOS)
        </div>
    </div>
</body>
</html>
