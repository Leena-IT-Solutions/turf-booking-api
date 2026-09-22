@php
    $saasName = $saasSetting?->company_name ?: 'LEENA IT SOLUTIONS';
    $saasAddress = $saasSetting?->company_address ?: ($saasSetting?->address ?? 'Plot No 65, Shree Satyam CHS B101, Sai Section, Ambernath East 421501 MS India');
    $saasCity = $saasSetting?->city ?? 'Ambernath';
    $saasState = $saasSetting?->state ?? 'Maharashtra';
    $saasStateCode = $saasSetting?->state_code ?? '27';
    $saasGst = $saasSetting?->gst_number ?? '';
    $saasEmail = $saasSetting?->company_email ?: ($saasSetting?->contact_email ?? 'leenaadam28@gmail.com');
    $saasPhone = $saasSetting?->company_phone ?: ($saasSetting?->contact_mobile ?? '+91 9769409405');

    $platformTaxable = (float)$booking->platform_fee;
    $platformGst = (float)$booking->platform_fee_gst;
    $platformTotal = round($platformTaxable + $platformGst, 2);

    $isIgst = (float)($booking->platform_fee_igst ?? 0) > 0;
    $igstAmount = (float)($booking->platform_fee_igst ?? 0);
    $cgstAmount = (float)($booking->platform_fee_cgst ?? 0);
    $sgstAmount = (float)($booking->platform_fee_sgst ?? 0);

    // Same reasoning as the turf page: this page only bills the platform's own share of
    // the booking, so "Paid" here must be this page's proportional share of what's actually
    // been paid so far -- not an assumption that the platform fee was paid in full.
    $bookingTotalAmount = (float) $booking->total_amount;
    $totalPaidAcrossBooking = (float) $booking->payments->sum('amount');
    $platformPaidShare = $bookingTotalAmount > 0
        ? round($totalPaidAcrossBooking * ($platformTotal / $bookingTotalAmount), 2)
        : 0.00;
    $platformBalanceDue = max(0.00, round($platformTotal - $platformPaidShare, 2));
@endphp

<!-- Header Row -->
<table style="margin-bottom: 14px;">
    <tr>
        <td style="width: 60%; vertical-align: top; padding: 0;">
            <h1 class="header-title">{{ $saasName }}</h1>
            <div style="font-size: 10.5px; font-weight: 600; color: #475569;">Technology & Booking Platform Provider</div>
            <div style="color: #475569; font-size: 9.5px; margin-top: 3px; line-height: 1.35;">
                {{ $saasAddress }}<br>
                State: <strong>{{ $saasState }}</strong> (State Code: <strong>{{ $saasStateCode }}</strong>)<br>
                Phone: {{ $saasPhone }} &nbsp;|&nbsp; Email: {{ $saasEmail }}
                @if($isGst && $saasGst)
                    <br><strong style="color: #0f172a;">GSTIN: {{ $saasGst }}</strong>
                @endif
            </div>
        </td>
        <td style="width: 40%; text-align: right; vertical-align: top; padding: 0;">
            <div class="doc-badge {{ $isGst ? 'doc-badge-gst' : 'doc-badge-nongst' }}">
                {{ $isGst ? 'TAX INVOICE (PLATFORM CONVENIENCE FEE)' : 'PLATFORM FEE RECEIPT' }}
            </div>
            <div style="margin-top: 6px; font-size: 9.5px; line-height: 1.4;">
                @if($isGst)
                    <div>Invoice No: <strong>{{ $booking->saas_invoice_number ?? 'PENDING' }}</strong></div>
                @else
                    <div>Receipt No: <strong>PREF-{{ $booking->booking_reference }}</strong></div>
                @endif
                <div>Invoice Date: <strong>{{ now('Asia/Kolkata')->format('d M Y') }}</strong></div>
                <div>Booking Ref: <strong>{{ $booking->booking_reference }}</strong></div>
                <div>Turf Venue: {{ $booking->turf->name ?? 'Turf' }}</div>
            </div>
        </td>
    </tr>
</table>

<!-- Customer & Supply Details -->
<table style="margin-bottom: 14px;">
    <tr>
        <td style="width: 50%; padding: 0 6px 0 0;">
            <div class="card">
                <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 4px;">
                    Billed To (Customer Details)
                </div>
                <div style="font-size: 11px; font-weight: 700; color: #0f172a;">
                    {{ $booking->user->name ?? 'Customer' }}
                </div>
                <div style="font-size: 9.5px; color: #475569; margin-top: 2px;">
                    Mobile: {{ $booking->user->mobile ?? 'N/A' }}<br>
                    Email: {{ $booking->user->email ?? 'N/A' }}
                    @if($booking->customer_company_name)
                        <br>Company: <strong>{{ $booking->customer_company_name }}</strong>
                    @endif
                    @if($booking->customer_gstin)
                        <br>Customer GSTIN: <strong>{{ $booking->customer_gstin }}</strong>
                    @endif
                </div>
            </div>
        </td>
        <td style="width: 50%; padding: 0 0 0 6px;">
            <div class="card">
                <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 4px;">
                    Platform Service Details
                </div>
                <div style="font-size: 9.5px; color: #475569;">
                    Service Category: <strong>Platform Technology & Booking Convenience</strong><br>
                    Supply Type: <strong>{{ $isIgst ? 'Inter-State Supply (IGST)' : 'Intra-State Supply (CGST + SGST)' }}</strong><br>
                    Linked Booking: <strong>{{ $booking->booking_reference }}</strong><br>
                    Payment Mode: <strong>Online Gateway</strong>
                </div>
            </div>
        </td>
    </tr>
</table>

<!-- Line Item Table -->
<div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #334155; margin-bottom: 4px;">
    Service Charges Breakdown
</div>
<table style="margin-bottom: 14px;">
    <thead>
        <tr>
            <th style="width: 8%;" class="text-center">#</th>
            <th style="width: {{ $isGst ? '52%' : '67%' }};">Service Description</th>
            @if($isGst)
                <th style="width: 15%;" class="text-center">SAC Code</th>
            @endif
            <th style="width: 25%;" class="text-right">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">1</td>
            <td>
                <strong>Platform Convenience Fee</strong>
                <div style="color: #64748b; font-size: 8.5px; margin-top: 2px;">
                    Online technology access, instant turf slot reservation & booking management service.
                </div>
            </td>
            @if($isGst)
                <td class="text-center font-bold">{{ $bookingGstSac }}</td>
            @endif
            <td class="text-right font-bold">{{ number_format($platformTaxable, 2) }}</td>
        </tr>
    </tbody>
</table>

<!-- Financial & Tax Summary Table -->
<table style="margin-bottom: 14px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding: 0 10px 0 0;">
            <div class="card">
                <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 3px;">
                    Tax Information
                </div>
                <div style="font-size: 9px; color: #64748b; line-height: 1.35;">
                    @if($isGst)
                        GST charged at 18% on technology platform convenience fee. Statutory credit is available for GST registered business entities.
                    @else
                        This platform fee receipt is issued by the technology operator. No input tax credit is applicable.
                    @endif
                </div>
            </div>
        </td>
        <td style="width: 50%; vertical-align: top; padding: 0 0 0 10px;">
            <table class="summary-table">
                @if($isGst)
                    <tr>
                        <td class="text-muted">Taxable Base Amount:</td>
                        <td class="text-right font-bold">₹{{ number_format($platformTaxable, 2) }}</td>
                    </tr>
                    @if($isIgst)
                        <tr>
                            <td class="text-muted">IGST (18%):</td>
                            <td class="text-right">₹{{ number_format($igstAmount, 2) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="text-muted">CGST (9%):</td>
                            <td class="text-right">₹{{ number_format($cgstAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">SGST (9%):</td>
                            <td class="text-right">₹{{ number_format($sgstAmount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total Platform Fee:</td>
                        <td class="text-right">₹{{ number_format($platformTotal, 2) }}</td>
                    </tr>
                @else
                    <tr class="total-row">
                        <td>Total Platform Fee:</td>
                        <td class="text-right">₹{{ number_format($platformTotal, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="text-muted" style="padding-top: 6px;">Paid via Online Payment:</td>
                    <td class="text-right font-bold" style="color: #047857; padding-top: 6px;">
                        ₹{{ number_format($platformPaidShare, 2) }}
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Balance Due:</td>
                    <td class="text-right font-bold" style="color: {{ $platformBalanceDue > 0 ? '#b45309' : '#475569' }};">
                        ₹{{ number_format($platformBalanceDue, 2) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Footer Signatures & Terms -->
<div class="footer-note">
    <table style="width: 100%;">
        <tr>
            <td style="width: 65%; border: none; padding: 0;">
                <strong>Terms of Platform Usage:</strong><br>
                1. Platform convenience fees cover digital infrastructure, slot locking, and notification services.<br>
                2. Platform fees are non-refundable in accordance with platform cancellation policy.<br>
                3. This is an electronically generated statutory document.
            </td>
            <td style="width: 35%; border: none; padding: 0; text-align: right; vertical-align: bottom;">
                <div style="font-weight: bold; color: #0f172a;">For {{ $saasName }}</div>
                <div style="margin-top: 25px; border-top: 1px solid #94a3b8; display: inline-block; padding-top: 4px; font-size: 8.5px; color: #475569;">
                    Authorized Signatory
                </div>
            </td>
        </tr>
    </table>
</div>
