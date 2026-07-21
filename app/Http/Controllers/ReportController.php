<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\Turf;
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

        $query = Booking::with(['turf', 'user', 'bookingDates.bookingSlots.slot', 'payments'])
            ->whereHas('bookingDates', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('booking_date', [$startDate, $endDate]);
            });

        if ($activeTurfId) {
            $query->where('turf_id', $activeTurfId);
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

        $query = Payment::with(['booking.turf', 'bookingDate'])
            ->where('status', 'Success')
            ->whereBetween(\DB::raw('DATE(paid_at)'), [$startDate, $endDate]);

        if ($activeTurfId) {
            $query->whereHas('booking', function ($q) use ($activeTurfId) {
                $q->where('turf_id', $activeTurfId);
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
}
