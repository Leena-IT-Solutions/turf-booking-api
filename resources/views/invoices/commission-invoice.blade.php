<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->is_gst_invoice ? 'Tax Invoice' : 'Commission Statement' }} - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 20px 24px;
        }
        body {
            /* DomPDF uses DejaVu Sans for full Unicode and Rupee sign (₹) support.
               Strictly avoid font-weight > 700 to prevent character corruption. */
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            font-size: 10px;
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
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 6px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        td {
            padding: 5px 6px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: 700;
        }
        .text-muted {
            color: #64748b;
        }
        .header-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: 700;
            border-radius: 4px;
            background-color: #e2e8f0;
            color: #334155;
            text-transform: uppercase;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
            background-color: #ffffff;
        }
        .bg-light {
            background-color: #f8fafc;
        }
        .divider {
            border-top: 1px solid #e2e8f0;
            margin: 10px 0;
        }
        .summary-table td {
            padding: 4px 6px;
            border-bottom: none;
        }
        .total-row td {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            font-weight: 700;
            background-color: #f8fafc;
        }
        .footer {
            margin-top: 14px;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    @php
        $supplier = $invoice->supplier_snapshot ?? [];
        $recipient = $invoice->recipient_snapshot ?? [];
        $startDate = \Carbon\Carbon::parse($invoice->period_start)->format('d M Y');
        $endDate = \Carbon\Carbon::parse($invoice->period_end)->format('d M Y');
        $billingPeriod = "{$startDate} – {$endDate}";
    @endphp

    <div class="page">
        <!-- Document Header -->
        <table style="margin-bottom: 12px;">
            <tr>
                <td style="width: 55%; border: none; padding: 0;">
                    <div class="header-title">
                        {{ $invoice->is_gst_invoice ? 'TAX INVOICE' : 'COMMISSION STATEMENT' }}
                    </div>
                    <div style="font-size: 11px; font-weight: 700; color: #10b981; margin-top: 2px;">
                        Platform Commission
                    </div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 1px;">
                        SAC: {{ $supplier['commission_gst_sac'] ?? '998599' }} &bull; Period: {{ $billingPeriod }}
                    </div>
                </td>
                <td style="width: 45%; text-align: right; border: none; padding: 0;">
                    <table style="width: auto; float: right; font-size: 9.5px;">
                        <tr>
                            <td style="padding: 1px 6px; text-align: right; color: #64748b; border: none;">Invoice Number:</td>
                            <td style="padding: 1px 6px; font-weight: 700; text-align: right; border: none;">{{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 1px 6px; text-align: right; color: #64748b; border: none;">Date of Issue:</td>
                            <td style="padding: 1px 6px; font-weight: 700; text-align: right; border: none;">{{ $invoice->generated_at ? $invoice->generated_at->format('d M Y') : now()->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 1px 6px; text-align: right; color: #64748b; border: none;">Status:</td>
                            <td style="padding: 1px 6px; text-align: right; border: none;">
                                <span class="badge badge-success">Settled via Wallet</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Supplier & Recipient Blocks -->
        <table style="margin-bottom: 14px;">
            <tr>
                <!-- Supplier (SaaS) Block -->
                <td style="width: 48%; padding: 0; border: none;">
                    <div class="card bg-light" style="margin-bottom: 0; min-height: 105px;">
                        <div style="font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">
                            Issued By (Platform Provider)
                        </div>
                        <div style="font-size: 11px; font-weight: 700; color: #0f172a;">
                            {{ $supplier['company_name'] ?? config('app.name') }}
                        </div>
                        @if(!empty($supplier['company_address']))
                            <div style="font-size: 9px; color: #475569; margin-top: 2px;">
                                {{ $supplier['company_address'] }}
                            </div>
                        @endif
                        <div style="font-size: 9px; color: #475569; margin-top: 2px;">
                            State: {{ $supplier['state'] ?? 'Maharashtra' }}
                            @if(!empty($supplier['state_code']))
                                (Code: {{ $supplier['state_code'] }})
                            @endif
                        </div>
                        @if(!empty($supplier['gst_number']))
                            <div style="font-size: 9px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                                GSTIN: {{ $supplier['gst_number'] }}
                            </div>
                        @endif
                        @if(!empty($supplier['company_email']))
                            <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">
                                Email: {{ $supplier['company_email'] }}
                            </div>
                        @endif
                    </div>
                </td>

                <td style="width: 4%; border: none;"></td>

                <!-- Recipient (Turf Owner) Block -->
                <td style="width: 48%; padding: 0; border: none;">
                    <div class="card" style="margin-bottom: 0; min-height: 105px;">
                        <div style="font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">
                            Billed To (Turf Partner)
                        </div>
                        <div style="font-size: 11px; font-weight: 700; color: #0f172a;">
                            {{ $recipient['company_name'] ?? 'Valued Partner' }}
                        </div>
                        @if(!empty($recipient['turf_name']) && $recipient['turf_name'] !== ($recipient['company_name'] ?? ''))
                            <div style="font-size: 9px; font-weight: 700; color: #475569; margin-top: 1px;">
                                Turf: {{ $recipient['turf_name'] }}
                            </div>
                        @endif
                        @if(!empty($recipient['address']))
                            <div style="font-size: 9px; color: #475569; margin-top: 2px;">
                                {{ $recipient['address'] }}
                            </div>
                        @endif
                        @if(!empty($recipient['state']))
                            <div style="font-size: 9px; color: #475569; margin-top: 2px;">
                                State: {{ $recipient['state'] }}
                                @if(!empty($recipient['state_code']))
                                    (Code: {{ $recipient['state_code'] }})
                                @endif
                            </div>
                        @endif
                        @php
                            $recipientStateCode = $recipient['state_code'] ?? null;
                            $recipientStateName = $recipient['state'] ?? ($recipientStateCode ? \App\Support\IndianStates::name($recipientStateCode) : null);
                        @endphp
                        @if($recipientStateCode || $recipientStateName)
                            <div style="font-size: 9px; color: #475569; margin-top: 2px;">
                                Place of Supply: {{ $recipientStateName ?? 'N/A' }}@if($recipientStateCode) ({{ $recipientStateCode }})@endif
                            </div>
                        @endif
                        @if(!empty($recipient['gst_number']))
                            <div style="font-size: 9px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                                GSTIN: {{ $recipient['gst_number'] }}
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Page 1 Executive Tax Summary Block -->
        <div class="card bg-light" style="padding: 10px 12px; margin-bottom: 14px;">
            <div style="font-size: 10px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                Tax & Settlement Summary
            </div>

            <table class="summary-table">
                <tr>
                    <td style="width: 50%; color: #475569; padding: 2px 0;">Taxable Commission Value:</td>
                    <td style="width: 50%; text-align: right; font-weight: 700; padding: 2px 0;">₹{{ number_format($invoice->taxable_amount, 2) }}</td>
                </tr>

                @if($invoice->is_gst_invoice)
                    @php
                        $hasIgst = (float)$invoice->igst_amount > 0;
                        $hasCgstSgst = ((float)$invoice->cgst_amount + (float)$invoice->sgst_amount) > 0;
                        $showDefaultCgstSgst = !$hasIgst && !$hasCgstSgst;
                        $halfRate = ($supplier['commission_gst_percentage'] ?? 18) / 2;
                    @endphp

                    @if($hasIgst)
                        <tr>
                            <td style="color: #475569; padding: 2px 0;">Integrated GST (IGST @ {{ $supplier['commission_gst_percentage'] ?? 18 }}%):</td>
                            <td style="text-align: right; font-weight: 700; padding: 2px 0;">₹{{ number_format($invoice->igst_amount, 2) }}</td>
                        </tr>
                    @endif

                    @if($hasCgstSgst || $showDefaultCgstSgst)
                        <tr>
                            <td style="color: #475569; padding: 2px 0;">Central GST (CGST @ {{ $halfRate }}%):</td>
                            <td style="text-align: right; font-weight: 700; padding: 2px 0;">₹{{ number_format($invoice->cgst_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #475569; padding: 2px 0;">State GST (SGST @ {{ $halfRate }}%):</td>
                            <td style="text-align: right; font-weight: 700; padding: 2px 0;">₹{{ number_format($invoice->sgst_amount, 2) }}</td>
                        </tr>
                    @endif
                @endif

                <tr style="border-top: 1px solid #cbd5e1;">
                    <td style="font-size: 11px; font-weight: 700; color: #0f172a; padding: 5px 0 2px 0;">Grand Total:</td>
                    <td style="font-size: 11px; font-weight: 700; color: #0f172a; text-align: right; padding: 5px 0 2px 0;">₹{{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size: 9px; color: #334155; padding: 2px 0; font-style: italic;">
                        Amount in words: {{ App\Support\NumberToWords::indianRupees((float)$invoice->total_amount) }}
                    </td>
                </tr>
            </table>

            <div class="divider" style="margin: 8px 0 6px 0;"></div>

            <table style="font-size: 8.5px; color: #475569;">
                <tr>
                    <td style="padding: 0; border: none; width: 70%;">
                        &bull; Amount already settled by deduction from your Turf Booking wallet; no payment is due against this invoice.
                        @if($invoice->is_gst_invoice && !empty($recipient['gst_number']))
                            <br>&bull; Input tax credit (ITC) can be claimed on this invoice subject to GST rules.
                        @endif
                    </td>
                    <td style="padding: 0; border: none; width: 30%; text-align: right; font-weight: 700; color: #059669;">
                        Total Bookings Charged: {{ $invoice->line_count }}
                    </td>
                </tr>
            </table>
        </div>

        <!-- Itemised Schedule of Bookings -->
        <div style="font-size: 10px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 12px 0 6px 0;">
            Schedule of Bookings & Deductions
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th style="width: 22%;">Booking Ref</th>
                    <th style="width: 20%;">Date Charged</th>
                    <th style="width: 14%; text-align: right;">Booking Amt</th>
                    <th style="width: 12%; text-align: right;">Taxable</th>
                    @if($invoice->is_gst_invoice)
                        <th style="width: 12%; text-align: right;">GST</th>
                    @endif
                    <th style="width: 14%; text-align: right;">Total Deducted</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->line_items ?? [] as $index => $item)
                    @php
                        $chargedDate = !empty($item['charged_at'])
                            ? \Carbon\Carbon::parse($item['charged_at'])->format('d M Y, h:i A')
                            : '—';
                        $gstTotal = (float)($item['cgst'] ?? 0) + (float)($item['sgst'] ?? 0) + (float)($item['igst'] ?? 0);
                        $isNegative = (float)($item['total'] ?? 0) < 0;
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $index + 1 }}</td>
                        <td class="font-bold">
                            {{ $item['booking_reference'] ?? ('#' . ($item['booking_id'] ?? '')) }}
                            @if(!empty($item['description']))
                                <div style="font-size: 8.5px; color: #dc2626; font-weight: normal;">{{ $item['description'] }}</div>
                            @endif
                        </td>
                        <td class="text-muted">{{ $chargedDate }}</td>
                        <td class="text-right">
                            @if((float)($item['booking_amount'] ?? 0) > 0)
                                ₹{{ number_format((float)$item['booking_amount'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right {{ (float)($item['taxable'] ?? 0) < 0 ? 'text-danger' : '' }}">
                            {{ (float)($item['taxable'] ?? 0) < 0 ? '-₹' . number_format(abs((float)$item['taxable']), 2) : '₹' . number_format((float)$item['taxable'], 2) }}
                        </td>
                        @if($invoice->is_gst_invoice)
                            <td class="text-right {{ $gstTotal < 0 ? 'text-danger' : '' }}">
                                {{ $gstTotal < 0 ? '-₹' . number_format(abs($gstTotal), 2) : '₹' . number_format($gstTotal, 2) }}
                            </td>
                        @endif
                        <td class="text-right font-bold {{ $isNegative ? 'text-danger' : '' }}">
                            {{ $isNegative ? '-₹' . number_format(abs((float)$item['total']), 2) : '₹' . number_format((float)$item['total'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $invoice->is_gst_invoice ? 7 : 6 }}" class="text-center text-muted" style="padding: 12px;">
                            No itemised records available.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;">Total:</td>
                    <td class="text-right">₹{{ number_format($invoice->taxable_amount, 2) }}</td>
                    @if($invoice->is_gst_invoice)
                        <td class="text-right">₹{{ number_format((float)($invoice->cgst_amount + $invoice->sgst_amount + $invoice->igst_amount), 2) }}</td>
                    @endif
                    <td class="text-right font-bold">₹{{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Document Footer -->
        <div class="footer">
            This is a computer-generated tax document and does not require a physical signature.<br>
            Generated on {{ now()->format('d M Y, h:i A') }} &bull; {{ $supplier['company_name'] ?? config('app.name') }}
        </div>
    </div>
</body>
</html>
