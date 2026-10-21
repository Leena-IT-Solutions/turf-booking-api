<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Services\BookingsLedgerService;
use App\Services\WalletLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Export Bookings Ledger report as CSV download (Part 6 layout).
     */
    public function exportBookings(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now('Asia/Kolkata')->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now('Asia/Kolkata')->endOfMonth()->toDateString());
        $dateBasis = $request->input('date_basis', 'session');
        $status = $request->input('status', 'all');
        $paymentStatus = $request->input('payment_status', 'all');
        $channel = $request->input('channel', 'all');
        $search = $request->input('search');

        $activeTurfId = session('active_turf_id');
        $manageableTurfIds = Turf::manageable(auth()->user())->pluck('id')->toArray();

        if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
            $turfIds = [$activeTurfId];
            $turfScopeName = Turf::find($activeTurfId)?->name ?? 'Turf';
        } else {
            $turfIds = $manageableTurfIds;
            $turfScopeName = count($turfIds) === 1 ? (Turf::find($turfIds[0])?->name ?? 'All Turfs') : 'All Turfs';
        }

        /** @var BookingsLedgerService $service */
        $service = app(BookingsLedgerService::class);
        $filters = [
            'date_basis' => $dateBasis,
            'start' => $startDate,
            'end' => $endDate,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'channel' => $channel,
            'search' => $search,
        ];

        $bookings = $service->query($turfIds, $filters)->get();
        $summary = $service->summary($turfIds, $filters);

        $filename = 'bookings_ledger_' . $startDate . '_to_' . $endDate . '.csv';

        $channelLabel = match ($channel) {
            'online' => 'App',
            'venue' => 'Venue',
            default => 'All',
        };

        $basisLabel = ($dateBasis === 'booked') ? 'booking date' : 'session date';

        return response()->streamDownload(function () use ($bookings, $summary, $service, $turfScopeName, $startDate, $endDate, $basisLabel, $channelLabel) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Title Line
            fputcsv($handle, [
                "# Bookings Ledger — {$turfScopeName} — {$startDate} to {$endDate} — by {$basisLabel} — channel {$channelLabel}"
            ]);

            // CSV Header Row
            fputcsv($handle, [
                'Booking',
                'Booked On',
                'Player',
                'Mobile',
                'Turf',
                'Sessions',
                'Hours',
                'Channel',
                'Booking Value (INR)',
                'Paid App (INR)',
                'Paid Cash (INR)',
                'Paid UPI (INR)',
                'Paid Other (INR)',
                'Refunded (INR)',
                'Net Paid (INR)',
                'Due (INR)',
                'Discount (INR)',
                'Coupon',
                'Booked By',
                'Status',
                'Payment Status',
            ]);

            $totalDiscount = 0.00;

            foreach ($bookings as $b) {
                $metrics = $service->rowMetrics($b);
                $totalDiscount += $metrics['discount'];

                $channelCol = match ($metrics['channel']) {
                    'online' => 'App',
                    'venue' => 'Venue',
                    'mixed' => 'Mixed',
                    default => '—',
                };

                fputcsv($handle, [
                    $b->booking_reference ?? ('#' . $b->id),
                    $b->created_at ? $b->created_at->format('Y-m-d H:i:s') : '—',
                    $b->user?->name ?? 'Guest',
                    $b->user?->mobile ?? '—',
                    $b->turf?->name ?? 'N/A',
                    implode(' | ', $metrics['sessions']),
                    number_format($metrics['total_hours'], 1, '.', ''),
                    $channelCol,
                    number_format($metrics['booking_value'], 2, '.', ''),
                    number_format($metrics['online_paid'], 2, '.', ''),
                    number_format($metrics['venue_split']['Cash'], 2, '.', ''),
                    number_format($metrics['venue_split']['UPI'], 2, '.', ''),
                    number_format($metrics['venue_split']['Other'], 2, '.', ''),
                    number_format($metrics['refunded'], 2, '.', ''),
                    number_format($metrics['net_paid'], 2, '.', ''),
                    number_format($metrics['balance_due'], 2, '.', ''),
                    number_format($metrics['discount'], 2, '.', ''),
                    implode(', ', $metrics['coupon_codes']) ?: '—',
                    $metrics['booked_by_label'],
                    $b->status ?? 'Confirmed',
                    $b->payment_status ?? 'Unpaid',
                ]);
            }

            // Blank line before Totals
            fputcsv($handle, []);

            // Totals row: Totals,,,,,,,,{value},{app},{cash},{upi},{other},{refunded},{net},{due},{discount}
            fputcsv($handle, [
                'Totals',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format($summary['booking_value'], 2, '.', ''),
                number_format($summary['online_collected'], 2, '.', ''),
                number_format($summary['venue_split']['Cash'], 2, '.', ''),
                number_format($summary['venue_split']['UPI'], 2, '.', ''),
                number_format($summary['venue_split']['Other'], 2, '.', ''),
                number_format($summary['refunded'], 2, '.', ''),
                number_format($summary['net_collected'], 2, '.', ''),
                number_format($summary['due_at_venue'], 2, '.', ''),
                number_format($totalDiscount, 2, '.', ''),
            ]);

            // Blank line before Summary Block
            fputcsv($handle, []);

            // Summary Block (Part 3)
            fputcsv($handle, ['Bookings', $summary['count']]);
            fputcsv($handle, ['Active', $summary['count_active']]);
            fputcsv($handle, ['Cancelled', $summary['count_cancelled']]);
            fputcsv($handle, ['Partly cancelled', $summary['count_partly_cancelled']]);
            fputcsv($handle, ['Booking value', number_format($summary['booking_value'], 2, '.', '')]);
            fputcsv($handle, ['Active value', number_format($summary['active_value'], 2, '.', '')]);
            fputcsv($handle, ['Cancelled value', number_format($summary['cancelled_value'], 2, '.', '')]);
            fputcsv($handle, ['Received', number_format($summary['received'], 2, '.', '')]);
            fputcsv($handle, ['Refunded', number_format($summary['refunded'], 2, '.', '')]);
            fputcsv($handle, ['Refund pending', number_format($summary['refund_pending'], 2, '.', '')]);
            fputcsv($handle, ['Net collected', number_format($summary['net_collected'], 2, '.', '')]);
            fputcsv($handle, ['Due at venue', number_format($summary['due_at_venue'], 2, '.', '')]);
            fputcsv($handle, ['By App received', number_format($summary['online_collected'], 2, '.', '')]);
            fputcsv($handle, [
                'At Location received',
                number_format($summary['venue_collected'], 2, '.', '') . ' (Cash ' . number_format($summary['venue_split']['Cash'], 2, '.', '') . ' · UPI ' . number_format($summary['venue_split']['UPI'], 2, '.', '') . ' · Other ' . number_format($summary['venue_split']['Other'], 2, '.', '') . ')'
            ]);
            fputcsv($handle, ['Cancelled sessions', $summary['cancelled_sessions']]);
            fputcsv($handle, ['Cancellation refunded', number_format($summary['cancellation_refunded'], 2, '.', '')]);
            fputcsv($handle, ['Kept by turf', number_format($summary['cancellation_kept_turf'], 2, '.', '')]);
            fputcsv($handle, ['Kept by platform', number_format($summary['cancellation_kept_platform'], 2, '.', '')]);
            fputcsv($handle, ['Coupon discounts', number_format($summary['discount_coupon'], 2, '.', '')]);
            fputcsv($handle, ['Staff discounts', number_format($summary['discount_staff'], 2, '.', '')]);
            fputcsv($handle, ['Hours booked', number_format($summary['active_hours'], 1, '.', '')]);
            fputcsv($handle, ['Avg booking value', number_format($summary['avg_booking_value'], 2, '.', '')]);
            fputcsv($handle, ['Collection rate %', number_format($summary['collection_rate'], 1, '.', '') . '%']);
            fputcsv($handle, ['Cancellation rate %', number_format($summary['cancellation_rate'], 1, '.', '') . '%']);

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
     * 1. Export Wallet Passbook Statement (Account-wide, running balance preserved, Part 5 layout).
     */
    public function exportWalletPassbook(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now('Asia/Kolkata')->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now('Asia/Kolkata')->endOfMonth()->toDateString());
        $group = $request->input('group', 'all');
        $groupFilter = ($group && $group !== 'all') ? $group : null;
        $includeZero = (bool) $request->input('zero', false);

        /** @var WalletLedgerService $service */
        $service = app(WalletLedgerService::class);
        $user = auth()->user();
        $owner = $service->ownerFor($user);

        $summary = $service->summary($owner, $startDate, $endDate);

        $query = $service->query($owner, $startDate, $endDate, $groupFilter, $includeZero);
        // Overwrite the order to OLDEST FIRST (accounting order)
        $txs = $query->reorder()
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $filename = 'wallet_passbook_' . $startDate . '_to_' . $endDate . '.csv';

        return response()->streamDownload(function () use ($txs, $owner, $startDate, $endDate, $summary) {
            $handle = fopen('php://output', 'w');
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['# Wallet Passbook — ' . $owner->name . ' — ' . $startDate . ' to ' . $endDate]);
            fputcsv($handle, ['Opening balance (INR)', number_format($summary['opening_balance'], 2, '.', '')]);
            fputcsv($handle, ['Closing balance (INR)', number_format($summary['closing_balance'], 2, '.', '')]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Date',
                'Time',
                'Booking',
                'Turf',
                'Type',
                'Description',
                'Amount (INR)',
                'Balance After (INR)',
            ]);

            foreach ($txs as $tx) {
                $amount = (float) $tx->amount;
                $label = CommissionWalletTransaction::typeLabel($tx->type, $amount);
                $cleanType = trim(preg_replace('/^[+\-]\s*/', '', $label));

                $booking = WalletLedgerService::bookingFor($tx);
                if ($booking) {
                    $bookingCol = $booking->booking_reference ?? ('#' . $booking->id);
                } elseif ($tx->reference instanceof TurfPayout || in_array($tx->type, ['payout_debit', 'payout_reversal'], true)) {
                    $bookingCol = 'Payout #' . ($tx->reference_id ?? $tx->id);
                } else {
                    $bookingCol = '—';
                }

                $turfName = WalletLedgerService::turfNameFor($tx);

                fputcsv($handle, [
                    $tx->created_at->format('Y-m-d'),
                    $tx->created_at->format('H:i:s'),
                    $bookingCol,
                    $turfName,
                    $cleanType,
                    $tx->description ?? '',
                    number_format($amount, 2, '.', ''),
                    number_format((float) $tx->balance_after, 2, '.', ''),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Total credits (INR)', number_format($summary['period_credits'], 2, '.', '')]);
            fputcsv($handle, ['Total debits (INR)', number_format($summary['period_debits'], 2, '.', '')]);

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

            // Same rule as the Business & Earnings panel: online money still to be credited.
            $pendingClearance = (float) (new \App\Services\WalletService())->pendingCredits($turfIds)->sum('amount');

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
                'commission_reversal',
                'gateway_charge_debit',
                'gateway_charge_reversal',
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
                $commission = max(0.0, round(abs((float) $group->where('type', 'commission_debit')->sum('amount')) - (float) $group->where('type', 'commission_reversal')->sum('amount'), 2));
                $pgCharges = max(0.0, round(abs((float) $group->where('type', 'gateway_charge_debit')->sum('amount')) - (float) $group->where('type', 'gateway_charge_reversal')->sum('amount'), 2));
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
                'Mode',
                'Requested Amount (INR)',
                'Charge Applied (INR)',
                'Net Disbursed (INR)',
                'Status',
                'UTR',
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
                    ucfirst($p->mode ?? 'manual'),
                    number_format($req, 2, '.', ''),
                    number_format($charge, 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    $p->status ?? 'pending',
                    $p->utr ?? 'N/A',
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
