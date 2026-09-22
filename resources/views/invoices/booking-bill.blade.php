<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bill - {{ $booking->booking_reference }}</title>
    <style>
        @page {
            margin: 20px 24px;
        }
        body {
            /* DomPDF maps Helvetica/Arial/sans-serif to its built-in PDF core fonts, which
               are ASCII-only and have no glyph for the Rupee sign (renders as "?"). DejaVu
               Sans is the TTF dompdf actually ships and embeds, and it covers ₹ (U+20B9). */
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            font-size: 10.5px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .page {
            position: relative;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        td {
            padding: 6px 8px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .text-muted {
            color: #64748b;
        }
        .header-title {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .doc-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        .doc-badge-gst {
            background-color: #047857;
            color: #ffffff;
        }
        .doc-badge-nongst {
            background-color: #2563eb;
            color: #ffffff;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .summary-table td {
            padding: 4px 6px;
            border: none;
        }
        .total-row {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
        }
        .slot-tag {
            display: inline-block;
            background-color: #e2e8f0;
            color: #1e293b;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            margin: 1px;
        }
        .footer-note {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
            color: #64748b;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="page">
        @include('invoices.partials.turf-bill', [
            'booking' => $booking,
            'isGst' => $turfIsGstActive,
            'turfSetting' => $turfSetting,
            'bookingGstSac' => $bookingGstSac,
        ])
    </div>

    @if ($includeSaasPage)
        <div style="page-break-after: always;"></div>
        <div class="page">
            @include('invoices.partials.saas-bill', [
                'booking' => $booking,
                'isGst' => $saasIsGstActive,
                'saasSetting' => $saasSetting,
                'bookingGstSac' => $bookingGstSac,
            ])
        </div>
    @endif
</body>
</html>
