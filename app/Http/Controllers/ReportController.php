<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\Turf;
use App\Models\TurfPayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Export detailed Bookings report as CSV download.
     */
    public function exportBookings(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $activeTurfId = session('active_turf_id');
        $manageableTurfIds = Turf::manageable(auth()->user())->pluck('id')->toArray();

        $query = Booking::with(['turf', 'user', 'bookingDates.bookingSlots.slot', 'payments'])
            ->whereHas('bookingDates', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('booking_date', [$startDate, $endDate]);
            });

        if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
            $query->where('turf_id', $activeTurfId);
        } else {
            $query->whereIn('turf_id', $manageableTurfIds);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($paymentStatus && $paymentStatus !== 'all') {
            $query->where('payment_status', $paymentStatus);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        $filename = 'bookings_report_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($bookings) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV Header Row
            fputcsv($handle, [
                'Booking Ref',
                'Turf Name',
                'Customer Name',
                'Mobile Number',
                'Email',
                'Booking Type',
                'Booking Dates',
                'Time Range / Slots',
                'Total Amount (INR)',
                'Paid Amount (INR)',
                'Balance (INR)',
                'Payment Status',
                'Booking Status',
                'Created At',
            ]);

            foreach ($bookings as $b) {
                $datesStr = $b->bookingDates->pluck('booking_date')->implode(', ');
                
                // Determine slot summary
                $allSlots = [];
                foreach ($b->bookingDates as $bd) {
                    foreach ($bd->bookingSlots as $bs) {
                        if ($bs->slot) {
                            $from = date('h:i A', strtotime($bs->slot->from_time));
                            $to = date('h:i A', strtotime($bs->slot->to_time));
                            $allSlots[] = "$from - $to";
                        }
                    }
                }
                $slotsStr = implode(' | ', array_unique($allSlots));

                $totalAmount = (float)$b->bookingDates->where('status', '!=', 'Cancelled')->sum('amount');
                $paidAmount = (float)$b->payments->where('status', 'Success')->sum('amount');
                $balance = max(0.00, $totalAmount - $paidAmount);

                fputcsv($handle, [
                    $b->booking_reference ?? ('#' . $b->id),
                    $b->turf?->name ?? 'N/A',
                    $b->user?->name ?? 'Guest / Manual',
                    $b->user?->mobile ?? 'N/A',
                    $b->user?->email ?? 'N/A',
                    ucfirst($b->booking_type ?? 'day'),
                    $datesStr,
                    $slotsStr,
                    number_format($totalAmount, 2, '.', ''),
                    number_format($paidAmount, 2, '.', ''),
                    number_format($balance, 2, '.', ''),
                    $b->payment_status ?? 'Unpaid',
                    $b->status ?? 'Confirmed',
                    $b->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export Revenue summary report by payment method as CSV download.
     */
    public function exportRevenue(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $activeTurfId = session('active_turf_id');
        $manageableTurfIds = Turf::manageable(auth()->user())->pluck('id')->toArray();

        $query = Payment::with(['booking.turf', 'bookingDate'])
            ->where('status', 'Success')
            ->whereBetween(\DB::raw('DATE(paid_at)'), [$startDate, $endDate]);

        if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
            $query->whereHas('booking', function ($q) use ($activeTurfId) {
                $q->where('turf_id', $activeTurfId);
            });
        } else {
            $query->whereHas('booking', function ($q) use ($manageableTurfIds) {
                $q->whereIn('turf_id', $manageableTurfIds);
            });
        }

        $payments = $query->get();

        $filename = 'revenue_report_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($payments, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Payment ID',
                'Paid Date & Time',
                'Booking Ref',
                'Turf Name',
                'Payment Method',
                'Amount Paid (INR)',
            ]);

            $totalCash = 0.00;
            $totalUPI = 0.00;
            $totalApp = 0.00;
            $totalOther = 0.00;

            foreach ($payments as $p) {
                $amount = (float)$p->amount;
                $method = $p->payment_method ?? 'App';

                if ($method === 'Cash') $totalCash += $amount;
                elseif ($method === 'UPI') $totalUPI += $amount;
                elseif ($method === 'App') $totalApp += $amount;
                else $totalOther += $amount;

                fputcsv($handle, [
                    '#PAY-' . $p->id,
                    Carbon::parse($p->paid_at)->format('Y-m-d H:i:s'),
                    $p->booking?->booking_reference ?? ('#' . $p->booking_id),
                    $p->booking?->turf?->name ?? 'N/A',
                    $method,
                    number_format($amount, 2, '.', ''),
                ]);
            }

            // Summary Totals
            fputcsv($handle, []);
            fputcsv($handle, ['--- REVENUE SUMMARY ---']);
            fputcsv($handle, ['Cash Revenue (INR)', number_format($totalCash, 2, '.', '')]);
            fputcsv($handle, ['UPI Revenue (INR)', number_format($totalUPI, 2, '.', '')]);
            fputcsv($handle, ['App / Online Gateway (INR)', number_format($totalApp, 2, '.', '')]);
            fputcsv($handle, ['Other Revenue (INR)', number_format($totalOther, 2, '.', '')]);
            fputcsv($handle, ['TOTAL REVENUE (INR)', number_format($totalCash + $totalUPI + $totalApp + $totalOther, 2, '.', '')]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 1. Export Wallet Passbook Statement (Account-wide, running balance preserved).
     */
    public function exportWalletPassbook(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $ownerId = auth()->user()->getOwnerId();

        $txs = CommissionWalletTransaction::where('user_id', $ownerId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->orderBy('id', 'asc')
            ->get();

        $filename = 'wallet_passbook_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($txs) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['# Wallet Passbook Statement (Account-Wide across all owned turfs)']);
            fputcsv($handle, [
                'Tx ID',
                'Date & Time',
                'Transaction Type',
                'Description',
                'Amount (INR)',
                'Running Balance (INR)',
            ]);

            foreach ($txs as $tx) {
                $amount = (float) $tx->amount;
                $label = CommissionWalletTransaction::typeLabel($tx->type, $amount);

                fputcsv($handle, [
                    '#TX-' . $tx->id,
                    $tx->created_at->format('Y-m-d H:i:s'),
                    $label,
                    $tx->description ?? '',
                    number_format($amount, 2, '.', ''),
                    number_format((float) $tx->balance_after, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 2. Export Turf Earnings & Deductions Breakdown (Turf-scoped).
     */
    public function exportTurfEarningsBreakdown(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $activeTurfId = session('active_turf_id');
        $manageableTurfIds = Turf::manageable(auth()->user())->pluck('id')->toArray();

        $turfIds = ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) ? [$activeTurfId] : $manageableTurfIds;

        $payments = Payment::with(['booking.turf', 'bookingDate'])
            ->whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
            ->where('status', 'Success')
            ->whereBetween(\DB::raw('DATE(paid_at)'), [$startDate, $endDate])
            ->orderBy('paid_at', 'desc')
            ->get();

        $filename = 'turf_earnings_breakdown_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($payments, $turfIds) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Payment Ref',
                'Paid At',
                'Booking Ref',
                'Turf Name',
                'Payment Method',
                'Gross Amount (INR)',
                'Platform Fee (INR)',
                'Commission (INR)',
                'PG Charges (INR)',
                'Net Payout (INR)',
                'Clearance Status',
            ]);

            $totalGross = 0.0;
            $totalPlatformFee = 0.0;
            $totalCommission = 0.0;
            $totalPgCharges = 0.0;
            $totalNetPayout = 0.0;

            foreach ($payments as $p) {
                $gross = (float) $p->amount;
                $comm = (float) $p->commission_amount;
                $pg = (float) $p->gateway_charge_amount;
                $net = (float) $p->turf_payout_amount;
                $clearanceStatus = $p->wallet_cleared_at ? 'Cleared' : 'Pending';

                // Display platform fee only on payments where deductions were settled
                $platFee = 0.0;
                if ($p->deductions_settled_at && $p->booking) {
                    $platFee = (float) ($p->booking->platform_fee + ($p->booking->platform_fee_gst ?? 0));
                }

                $totalGross += $gross;
                $totalPlatformFee += $platFee;
                $totalCommission += $comm;
                $totalPgCharges += $pg;
                $totalNetPayout += $net;

                fputcsv($handle, [
                    '#PAY-' . $p->id,
                    Carbon::parse($p->paid_at)->format('Y-m-d H:i:s'),
                    $p->booking?->booking_reference ?? ('#' . $p->booking_id),
                    $p->booking?->turf?->name ?? 'N/A',
                    $p->payment_method ?? 'App',
                    number_format($gross, 2, '.', ''),
                    number_format($platFee, 2, '.', ''),
                    number_format($comm, 2, '.', ''),
                    number_format($pg, 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    $clearanceStatus,
                ]);
            }

            $pendingClearance = (float) Payment::whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
                ->where('status', 'Success')
                ->whereNull('wallet_cleared_at')
                ->where('turf_payout_amount', '>', 0)
                ->sum('turf_payout_amount');

            // Summary totals
            fputcsv($handle, []);
            fputcsv($handle, ['--- EARNINGS & PAYOUT SUMMARY ---']);
            fputcsv($handle, ['Total Gross Revenue (INR)', number_format($totalGross, 2, '.', '')]);
            fputcsv($handle, ['Total Platform Fee (INR)', number_format($totalPlatformFee, 2, '.', '')]);
            fputcsv($handle, ['Total Commission (INR)', number_format($totalCommission, 2, '.', '')]);
            fputcsv($handle, ['Total PG Charges (INR)', number_format($totalPgCharges, 2, '.', '')]);
            fputcsv($handle, ['Total Net Payout (INR)', number_format($totalNetPayout, 2, '.', '')]);
            fputcsv($handle, ['Pending Wallet Clearance (INR)', number_format($pendingClearance, 2, '.', '')]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 3. Export Turf GST Output Tax Report (Turf-scoped).
     */
    public function exportTurfGstReport(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $activeTurfId = session('active_turf_id');
        $manageableTurfIds = Turf::manageable(auth()->user())->pluck('id')->toArray();

        $turfIds = ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) ? [$activeTurfId] : $manageableTurfIds;

        $bookingDates = BookingDate::with(['booking.turf.turfSetting'])
            ->whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->where('status', '!=', 'Cancelled')
            ->orderBy('booking_date', 'desc')
            ->get();

        $filename = 'turf_gst_report_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($bookingDates, $turfIds) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            $isSingleTurf = count($turfIds) === 1;
            if ($isSingleTurf) {
                $turf = Turf::with('turfSetting')->find($turfIds[0]);
                $setting = $turf?->turfSetting;
                $gstin = ($setting?->is_gst_billing_active && !empty($setting->gst_number)) ? $setting->gst_number : 'Not registered';
                $company = $setting?->company_name ?? ($turf?->name ?? 'Turf');
                $state = $setting?->state ?? 'N/A';
                $stateCode = $setting?->state_code ?? 'N/A';

                fputcsv($handle, ['# Turf GST Output Tax Report']);
                fputcsv($handle, ['# Turf Name:', $turf?->name ?? 'N/A']);
                fputcsv($handle, ['# Company Name:', $company]);
                fputcsv($handle, ['# Turf GSTIN:', $gstin]);
                fputcsv($handle, ['# State / Code:', "$state ($stateCode)"]);
                fputcsv($handle, []);
            } else {
                fputcsv($handle, ['# Turf GST Output Tax Report (Multi-Turf Consolidated)']);
                fputcsv($handle, []);
            }

            $headers = [
                'Booking Ref',
                'Slot Date',
                'Turf Name',
            ];
            if (!$isSingleTurf) {
                $headers[] = 'Turf GSTIN';
            }
            $headers = array_merge($headers, [
                'Taxable Value (INR)',
                'CGST (INR)',
                'SGST (INR)',
                'Total GST (INR)',
                'Gross Amount (INR)',
            ]);

            fputcsv($handle, $headers);

            $totalTaxable = 0.0;
            $totalCgst = 0.0;
            $totalSgst = 0.0;
            $totalGst = 0.0;
            $totalGross = 0.0;

            foreach ($bookingDates as $bd) {
                $taxable = (float) $bd->taxable_amount;
                $cgst = (float) $bd->turf_cgst_amount;
                $sgst = (float) $bd->turf_sgst_amount;
                $gst = (float) $bd->turf_gst_amount;
                $gross = (float) $bd->amount;

                $totalTaxable += $taxable;
                $totalCgst += $cgst;
                $totalSgst += $sgst;
                $totalGst += $gst;
                $totalGross += $gross;

                $row = [
                    $bd->booking?->booking_reference ?? ('#' . $bd->booking_id),
                    $bd->booking_date,
                    $bd->booking?->turf?->name ?? 'N/A',
                ];

                if (!$isSingleTurf) {
                    $tSetting = $bd->booking?->turf?->turfSetting;
                    $row[] = ($tSetting?->is_gst_billing_active && !empty($tSetting->gst_number)) ? $tSetting->gst_number : 'Not registered';
                }

                $row[] = number_format($taxable, 2, '.', '');
                $row[] = number_format($cgst, 2, '.', '');
                $row[] = number_format($sgst, 2, '.', '');
                $row[] = number_format($gst, 2, '.', '');
                $row[] = number_format($gross, 2, '.', '');

                fputcsv($handle, $row);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['--- GST LIABILITY SUMMARY ---']);
            fputcsv($handle, ['Total Taxable Value (INR)', number_format($totalTaxable, 2, '.', '')]);
            fputcsv($handle, ['Total CGST Collected (INR)', number_format($totalCgst, 2, '.', '')]);
            fputcsv($handle, ['Total SGST Collected (INR)', number_format($totalSgst, 2, '.', '')]);
            fputcsv($handle, ['Total GST Liability (INR)', number_format($totalGst, 2, '.', '')]);
            fputcsv($handle, ['Total Gross Value (INR)', number_format($totalGross, 2, '.', '')]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 4. Export Cancellations & Refunds Report (Turf-scoped).
     */
    public function exportCancellationReport(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $activeTurfId = session('active_turf_id');
        $manageableTurfIds = Turf::manageable(auth()->user())->pluck('id')->toArray();

        $turfIds = ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) ? [$activeTurfId] : $manageableTurfIds;

        $cancellations = BookingCancellation::with(['booking.turf', 'bookingDate', 'cancelledByUser'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'cancellations_report_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($cancellations) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Cancellation ID',
                'Cancelled At',
                'Booking Ref',
                'Turf Name',
                'Cancelled By',
                'Gross Cancelled (INR)',
                'Turf Fee Retained (INR)',
                'SaaS Fee Retained (INR)',
                'Refund Amount (INR)',
                'Refund Status',
                'Resolution Mode',
                'Reason',
            ]);

            $totalGross = 0.0;
            $totalTurfFee = 0.0;
            $totalSaasFee = 0.0;
            $totalRefund = 0.0;

            foreach ($cancellations as $c) {
                $gross = (float) $c->gross_cancelled_amount;
                $turfFee = (float) $c->turf_cancellation_fee;
                $saasFee = (float) $c->saas_cancellation_fee;
                $refund = (float) $c->refund_amount;

                $totalGross += $gross;
                $totalTurfFee += $turfFee;
                $totalSaasFee += $saasFee;
                $totalRefund += $refund;

                fputcsv($handle, [
                    '#CAN-' . $c->id,
                    $c->created_at->format('Y-m-d H:i:s'),
                    $c->booking?->booking_reference ?? ('#' . $c->booking_id),
                    $c->booking?->turf?->name ?? 'N/A',
                    $c->canceller_role ?? ($c->cancelledByUser?->name ?? 'User'),
                    number_format($gross, 2, '.', ''),
                    number_format($turfFee, 2, '.', ''),
                    number_format($saasFee, 2, '.', ''),
                    number_format($refund, 2, '.', ''),
                    $c->refund_status ?? 'N/A',
                    $c->resolution_mode ?? 'N/A',
                    $c->reason ?? '',
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['--- CANCELLATION SUMMARY ---']);
            fputcsv($handle, ['Total Gross Cancelled (INR)', number_format($totalGross, 2, '.', '')]);
            fputcsv($handle, ['Total Turf Fee Retained (INR)', number_format($totalTurfFee, 2, '.', '')]);
            fputcsv($handle, ['Total SaaS Fee Retained (INR)', number_format($totalSaasFee, 2, '.', '')]);
            fputcsv($handle, ['Total Refund Disbursed (INR)', number_format($totalRefund, 2, '.', '')]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 5. Export Commission & Fee Monthly Summary (Account-wide, driver-agnostic).
     */
    public function exportCommissionFeeSummary(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonths(6)->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $ownerId = auth()->user()->getOwnerId();

        $txs = CommissionWalletTransaction::where('user_id', $ownerId)
            ->whereIn('type', [
                'platform_fee_debit',
                'commission_debit',
                'gateway_charge_debit',
                'payment_credit',
            ])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->get()
            ->groupBy(fn($tx) => $tx->created_at->format('Y-m'));

        $filename = 'commission_fee_summary_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($txs) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['# Monthly Commission & Software Fee Summary (Account-Wide across all owned turfs)']);
            fputcsv($handle, [
                'Month',
                'Gross Revenue (INR)',
                'Platform Fee (INR)',
                'Commission (INR)',
                'PG Charges (INR)',
                'Total SaaS Deductions (INR)',
                'Effective Deduction Rate (%)',
            ]);

            $months = [];
            $totalGross = 0.0;
            $totalPlatformFee = 0.0;
            $totalCommission = 0.0;
            $totalPgCharges = 0.0;
            $totalDeductions = 0.0;

            foreach ($txs as $month => $group) {
                $gross = (float) $group->where('type', 'payment_credit')->sum('amount');
                $platformFee = abs((float) $group->where('type', 'platform_fee_debit')->sum('amount'));
                $commission = abs((float) $group->where('type', 'commission_debit')->sum('amount'));
                $pgCharges = abs((float) $group->where('type', 'gateway_charge_debit')->sum('amount'));
                $deductions = $platformFee + $commission + $pgCharges;
                $effectiveRate = $gross > 0 ? round(($deductions / $gross) * 100, 2) : 0.0;

                $totalGross += $gross;
                $totalPlatformFee += $platformFee;
                $totalCommission += $commission;
                $totalPgCharges += $pgCharges;
                $totalDeductions += $deductions;

                $months[] = [
                    'month' => $month,
                    'month_label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                    'gross' => $gross,
                    'platform_fee' => $platformFee,
                    'commission' => $commission,
                    'pg_charges' => $pgCharges,
                    'deductions' => $deductions,
                    'effective_rate' => $effectiveRate,
                ];
            }

            usort($months, fn($a, $b) => strcmp($b['month'], $a['month']));

            foreach ($months as $m) {
                fputcsv($handle, [
                    $m['month_label'],
                    number_format($m['gross'], 2, '.', ''),
                    number_format($m['platform_fee'], 2, '.', ''),
                    number_format($m['commission'], 2, '.', ''),
                    number_format($m['pg_charges'], 2, '.', ''),
                    number_format($m['deductions'], 2, '.', ''),
                    number_format($m['effective_rate'], 2, '.', '') . '%',
                ]);
            }

            $overallEffectiveRate = $totalGross > 0 ? round(($totalDeductions / $totalGross) * 100, 2) : 0.0;

            fputcsv($handle, []);
            fputcsv($handle, ['--- PERIOD TOTALS ---']);
            fputcsv($handle, ['Total Gross Revenue (INR)', number_format($totalGross, 2, '.', '')]);
            fputcsv($handle, ['Total Platform Fee (INR)', number_format($totalPlatformFee, 2, '.', '')]);
            fputcsv($handle, ['Total Commission (INR)', number_format($totalCommission, 2, '.', '')]);
            fputcsv($handle, ['Total PG Charges (INR)', number_format($totalPgCharges, 2, '.', '')]);
            fputcsv($handle, ['Total SaaS Deductions (INR)', number_format($totalDeductions, 2, '.', '')]);
            fputcsv($handle, ['Overall Effective Rate (%)', number_format($overallEffectiveRate, 2, '.', '') . '%']);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 6. Export Payout History (Account-wide).
     */
    public function exportPayoutHistory(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonths(6)->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $ownerId = auth()->user()->getOwnerId();

        $payouts = TurfPayout::where('user_id', $ownerId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'payout_history_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($payouts) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['# Bank Payout History (Account-Wide across all owned turfs)']);
            fputcsv($handle, [
                'Payout ID',
                'Requested At',
                'Requested Amount (INR)',
                'Charge Applied (INR)',
                'Net Disbursed (INR)',
                'Status',
                'Razorpay Payout ID',
                'Processed At',
                'Failure Reason',
            ]);

            $totalRequested = 0.0;
            $totalCharges = 0.0;
            $totalNet = 0.0;

            foreach ($payouts as $p) {
                $req = (float) $p->requested_amount;
                $charge = (float) $p->charge_applied;
                $net = (float) $p->net_amount;

                $totalRequested += $req;
                $totalCharges += $charge;
                $totalNet += $net;

                fputcsv($handle, [
                    '#PO-' . $p->id,
                    $p->created_at->format('Y-m-d H:i:s'),
                    number_format($req, 2, '.', ''),
                    number_format($charge, 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    $p->status ?? 'Pending',
                    $p->razorpay_payout_id ?? 'N/A',
                    $p->processed_at ? $p->processed_at->format('Y-m-d H:i:s') : 'N/A',
                    $p->failure_reason ?? '',
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['--- PAYOUT TOTALS ---']);
            fputcsv($handle, ['Total Requested (INR)', number_format($totalRequested, 2, '.', '')]);
            fputcsv($handle, ['Total Charges Applied (INR)', number_format($totalCharges, 2, '.', '')]);
            fputcsv($handle, ['Total Net Disbursed (INR)', number_format($totalNet, 2, '.', '')]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
