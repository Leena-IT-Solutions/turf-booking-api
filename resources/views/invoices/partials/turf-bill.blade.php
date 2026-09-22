@php
    $turfName = $booking->turf->name ?? 'Sports Turf';
    $companyName = $turfSetting?->company_name ?: $turfName;
    $address = $turfSetting?->address ?: ($booking->turf->location?->address ?? '');
    $city = $turfSetting?->city ?: ($booking->turf->location?->city ?? '');
    $state = $turfSetting?->state ?: ($booking->turf->location?->state ?? 'Maharashtra');
    $stateCode = $turfSetting?->state_code ?: '27';
    $pincode = $turfSetting?->pincode ?? '';
    $phone = $turfSetting?->company_phone ?? '';
    $email = $turfSetting?->company_email ?? '';
    $gstNumber = $turfSetting?->gst_number ?? '';

    $turfSubtotal = (float)($booking->taxable_amount > 0 ? $booking->taxable_amount : ($booking->total_amount - $booking->platform_fee - $booking->platform_fee_gst));
    $turfTotal = $isGst ? round($turfSubtotal + (float)$booking->turf_gst_amount, 2) : $turfSubtotal;
@endphp

<!-- Header Row -->
<table style="margin-bottom: 14px;">
    <tr>
        <td style="width: 60%; vertical-align: top; padding: 0;">
            <h1 class="header-title">{{ $companyName }}</h1>
            @if($companyName !== $turfName)
                <div style="font-size: 11px; font-weight: 600; color: #475569;">Facility: {{ $turfName }}</div>
            @endif
            <div style="color: #475569; font-size: 9.5px; margin-top: 3px; line-height: 1.35;">
                @if($address) {{ $address }}, @endif
                @if($city) {{ $city }} @endif
                @if($pincode) - {{ $pincode }} @endif
                <br>
                @if($state) State: <strong>{{ $state }}</strong> (State Code: <strong>{{ $stateCode }}</strong>) <br> @endif
                @if($phone) Phone: {{ $phone }} &nbsp;|&nbsp; @endif
                @if($email) Email: {{ $email }} @endif
                @if($isGst && $gstNumber)
                    <br><strong style="color: #0f172a;">GSTIN: {{ $gstNumber }}</strong>
                @endif
            </div>
        </td>
        <td style="width: 40%; text-align: right; vertical-align: top; padding: 0;">
            <div class="doc-badge {{ $isGst ? 'doc-badge-gst' : 'doc-badge-nongst' }}">
                {{ $isGst ? 'TAX INVOICE' : 'BOOKING RECEIPT' }}
            </div>
            <div style="margin-top: 6px; font-size: 9.5px; line-height: 1.4;">
                @if($isGst)
                    <div>Invoice No: <strong>{{ $booking->turf_invoice_number ?? 'PENDING' }}</strong></div>
                @else
                    <div>Receipt No: <strong>REC-{{ $booking->booking_reference }}</strong></div>
                @endif
                <div>Invoice Date: <strong>{{ now('Asia/Kolkata')->format('d M Y') }}</strong></div>
                <div>Booking Ref: <strong>{{ $booking->booking_reference }}</strong></div>
                <div>Booked On: {{ $booking->date_of_booking ? \Carbon\Carbon::parse($booking->date_of_booking)->format('d M Y h:i A') : 'N/A' }}</div>
            </div>
        </td>
    </tr>
</table>

<!-- Customer & Booking Info Cards -->
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
                    Session & Supply Details
                </div>
                <div style="font-size: 9.5px; color: #475569;">
                    Session Type: <strong>{{ ucfirst($booking->booking_type ?? 'day') }} Session</strong><br>
                    Place of Supply: <strong>{{ $state }} ({{ $stateCode }})</strong><br>
                    Status: <strong style="color: {{ $booking->status === 'Confirmed' ? '#047857' : '#b91c1c' }}">{{ $booking->status }}</strong><br>
                    Payment Status: <strong style="color: {{ $booking->payment_status === 'Paid' ? '#047857' : '#b45309' }}">{{ $booking->payment_status }}</strong>
                </div>
            </div>
        </td>
    </tr>
</table>

<!-- Itemized Booked Slots Table -->
<div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #334155; margin-bottom: 4px;">
    Booked Match Dates & Slots
</div>
<table style="margin-bottom: 14px;">
    <thead>
        <tr>
            <th style="width: 5%;" class="text-center">#</th>
            <th style="width: 25%;">Match Date</th>
            <th style="width: {{ $isGst ? '35%' : '50%' }};">Slot Timings / Description</th>
            @if($isGst)
                <th style="width: 15%;" class="text-center">SAC Code</th>
            @endif
            <th style="width: 20%;" class="text-right">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        @php $itemIndex = 1; @endphp
        @foreach($booking->bookingDates as $bDate)
            @php
                $slotList = [];
                foreach ($bDate->bookingSlots as $bSlot) {
                    if ($bSlot->slot) {
                        $f = $bSlot->slot->from_time ? date('h:i A', strtotime($bSlot->slot->from_time)) : '';
                        $t = $bSlot->slot->to_time ? date('h:i A', strtotime($bSlot->slot->to_time)) : '';
                        $slotList[] = ($f && $t) ? "$f - $t" : 'Slot';
                    }
                }
            @endphp
            <tr>
                <td class="text-center">{{ $itemIndex++ }}</td>
                <td>
                    <strong>{{ \Carbon\Carbon::parse($bDate->booking_date)->format('D, d M Y') }}</strong>
                    @if($bDate->status === 'Cancelled')
                        <span style="color: #b91c1c; font-size: 8.5px; font-weight: bold;">(Cancelled)</span>
                    @endif
                </td>
                <td>
                    <div>Turf Ground Session Booking</div>
                    <div style="margin-top: 2px;">
                        @foreach($slotList as $time)
                            <span class="slot-tag">{{ $time }}</span>
                        @endforeach
                    </div>
                </td>
                @if($isGst)
                    <td class="text-center font-bold">{{ $bookingGstSac }}</td>
                @endif
                <td class="text-right font-bold">
                    {{ number_format((float)$bDate->amount, 2) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- Financial & Tax Summary Table -->
<table style="margin-bottom: 14px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding: 0 10px 0 0;">
            <!-- Payment History Details -->
            <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #334155; margin-bottom: 4px;">
                Payment Records
            </div>
            @if($booking->payments->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th style="font-size: 8.5px; padding: 4px;">Method</th>
                            <th style="font-size: 8.5px; padding: 4px;">Date / Ref</th>
                            <th style="font-size: 8.5px; padding: 4px;" class="text-right">Paid (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($booking->payments as $pmt)
                            <tr>
                                <td style="font-size: 9px; padding: 4px;">
                                    <strong>{{ $pmt->payment_method }}</strong>
                                </td>
                                <td style="font-size: 8.5px; padding: 4px; color: #475569;">
                                    {{ $pmt->paid_at ? \Carbon\Carbon::parse($pmt->paid_at)->format('d M Y') : $pmt->created_at->format('d M Y') }}
                                    @if($pmt->gateway_payment_id)
                                        <br><span style="font-size: 8px;">{{ substr($pmt->gateway_payment_id, 0, 16) }}</span>
                                    @endif
                                </td>
                                <td style="font-size: 9px; padding: 4px;" class="text-right font-bold">
                                    {{ number_format((float)$pmt->amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div style="font-size: 9.5px; color: #64748b; font-style: italic;">
                    No payment recorded yet.
                </div>
            @endif
        </td>

        <td style="width: 50%; vertical-align: top; padding: 0 0 0 10px;">
            <table class="summary-table">
                @if($isGst)
                    <tr>
                        <td class="text-muted">Taxable Value:</td>
                        <td class="text-right font-bold">₹{{ number_format($turfSubtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">CGST ({{ number_format((float)$booking->turf_gst_rate / 2, 1) }}%):</td>
                        <td class="text-right">₹{{ number_format((float)$booking->turf_cgst_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">SGST ({{ number_format((float)$booking->turf_gst_rate / 2, 1) }}%):</td>
                        <td class="text-right">₹{{ number_format((float)$booking->turf_sgst_amount, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Turf Charge:</td>
                        <td class="text-right">₹{{ number_format($turfTotal, 2) }}</td>
                    </tr>
                @else
                    @if($booking->coupon_discount > 0 || $booking->additional_discount > 0)
                        <tr>
                            <td class="text-muted">Gross Amount:</td>
                            <td class="text-right">₹{{ number_format((float)($booking->actual_amount > 0 ? $booking->actual_amount : $turfSubtotal), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Discount Applied:</td>
                            <td class="text-right" style="color: #047857;">-₹{{ number_format((float)($booking->coupon_discount + $booking->additional_discount), 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total Booking Amount:</td>
                        <td class="text-right">₹{{ number_format($turfTotal, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="text-muted" style="padding-top: 6px;">Total Paid:</td>
                    <td class="text-right font-bold" style="color: #047857; padding-top: 6px;">
                        ₹{{ number_format((float)$booking->payments->sum('amount'), 2) }}
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Balance Due:</td>
                    <td class="text-right font-bold" style="color: {{ (float)$booking->balance_amount > 0 ? '#b45309' : '#475569' }};">
                        ₹{{ number_format((float)$booking->balance_amount, 2) }}
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
                <strong>Terms & Conditions:</strong><br>
                1. Entry is strictly permitted only during the booked slot hours.<br>
                2. Cancellations and refunds are governed by the venue cancellation policy.<br>
                3. This is a computer-generated document and requires no physical signature.
            </td>
            <td style="width: 35%; border: none; padding: 0; text-align: right; vertical-align: bottom;">
                <div style="font-weight: bold; color: #0f172a;">For {{ $companyName }}</div>
                <div style="margin-top: 25px; border-top: 1px solid #94a3b8; display: inline-block; padding-top: 4px; font-size: 8.5px; color: #475569;">
                    Authorized Signatory
                </div>
            </td>
        </tr>
    </table>
</div>
