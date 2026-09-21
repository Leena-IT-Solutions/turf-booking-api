<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\BookingCancellation;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\BookingPricingCalculator;
use App\Services\CommissionCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Get bookings made by the authenticated user or manageable bookings for managers/admins.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isStaffOrAdmin = $user->hasAnyRole(['saas-admin', 'turf-admin', 'manager']);
        $filter = $request->query('filter', 'upcoming');
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        
        $query = BookingDate::with([
            'booking.turf',
            'booking.user',
            'booking.bookingDates.bookingSlots',
            'booking.bookingDates.payments',
            'bookingSlots.slot',
            'payments',
            'bookingCancellations',
        ]);

        $personal = $request->query('personal', false);
        $selectedTurfId = $request->query('turf_id');

        if ($isStaffOrAdmin && !$personal) {
            if (!$user->hasRole('saas-admin')) {
                $manageableTurfIds = $user->manageableTurfs()->pluck('turfs.id')->toArray();
                if ($selectedTurfId && in_array($selectedTurfId, $manageableTurfIds)) {
                    $query->whereHas('booking', function ($q) use ($selectedTurfId) {
                        $q->where('turf_id', $selectedTurfId);
                    });
                } else {
                    $query->whereHas('booking', function ($q) use ($manageableTurfIds) {
                        $q->whereIn('turf_id', $manageableTurfIds);
                    });
                }
            } elseif ($selectedTurfId) {
                $query->whereHas('booking', function ($q) use ($selectedTurfId) {
                    $q->where('turf_id', $selectedTurfId);
                });
            }
        } else {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $date = $request->query('date');

        if ($date) {
            if ($filter === 'past') {
                if ($date >= $today) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereDate('booking_date', $date)
                          ->where('status', '!=', 'Cancelled')
                          ->whereHas('booking', function ($q) {
                              $q->where('status', '!=', 'Cancelled');
                          });
                }
            } elseif ($filter === 'upcoming') {
                if ($date < $today) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereDate('booking_date', $date)
                          ->where('status', '!=', 'Cancelled')
                          ->whereHas('booking', function ($q) {
                              $q->where('status', '!=', 'Cancelled');
                          });
                }
            } elseif ($filter === 'cancelled') {
                $query->whereDate('booking_date', $date)
                      ->where(function ($q) {
                          $q->where('status', 'Cancelled')
                            ->orWhereHas('booking', function ($bq) {
                                $bq->where('status', 'Cancelled');
                            });
                      });
            } else {
                // 'all' filter
                $query->whereDate('booking_date', $date);
            }
        } else {
            if ($filter === 'past') {
                $query->where('booking_date', '<', $today)
                      ->where('status', '!=', 'Cancelled')
                      ->whereHas('booking', function ($q) {
                          $q->where('status', '!=', 'Cancelled');
                      })
                      ->orderBy('booking_date', 'desc');
            } elseif ($filter === 'upcoming') {
                $query->where('booking_date', '>=', $today)
                      ->where('status', '!=', 'Cancelled')
                      ->whereHas('booking', function ($q) {
                          $q->where('status', '!=', 'Cancelled');
                      })
                      ->orderBy('booking_date', 'asc');
            } elseif ($filter === 'cancelled') {
                $query->where(function ($q) {
                          $q->where('status', 'Cancelled')
                            ->orWhereHas('booking', function ($bq) {
                                $bq->where('status', 'Cancelled');
                            });
                      })
                      ->orderByRaw('COALESCE(cancelled_at, updated_at) DESC');
            } else {
                // 'all'
                $query->orderBy('booking_date', 'desc');
            }
        }

        $perPage = max(1, min(100, (int)$request->query('per_page', 10)));
        $bookingDates = $query->paginate($perPage);
            
        $formatted = $bookingDates->through(function ($bDate) {
            $booking = $bDate->booking;
            
            $slots = [];
            foreach ($bDate->bookingSlots as $bSlot) {
                $slot = $bSlot->slot;
                if ($slot) {
                    $slots[] = [
                        'id' => $slot->id,
                        'from_time' => $slot->from_time,
                        'to_time' => $slot->to_time,
                        'time_range' => ($slot->from_time && $slot->to_time)
                            ? date('h:i A', strtotime($slot->from_time)) . ' - ' . date('h:i A', strtotime($slot->to_time))
                            : 'N/A',
                        'duration' => $slot->duration,
                    ];
                }
            }

            usort($slots, function ($a, $b) {
                return strcmp($a['from_time'] ?? '', $b['from_time'] ?? '');
            });

            // Summary of slots for this date: Start Time - End Time
            $summaryText = '';
            if (!empty($slots)) {
                $firstSlot = $slots[0];
                $lastSlot = end($slots);

                $startTime = (!empty($firstSlot['from_time'])) ? date('h:i A', strtotime($firstSlot['from_time'])) : '';
                $endTime = (!empty($lastSlot['to_time'])) ? date('h:i A', strtotime($lastSlot['to_time'])) : '';

                if ($startTime && $endTime) {
                    $summaryText = $startTime . ' - ' . $endTime;
                } else {
                    $summaryText = $firstSlot['time_range'] ?? 'N/A';
                }
            } else {
                $summaryText = 'N/A';
            }

            // Calculate overall booking payment status metrics
            $totalBookingAmount = 0.00;
            $totalPaidAmount = 0.00;
            if ($booking) {
                $totalBookingAmount = (float) ($booking->total_amount > 0 ? $booking->total_amount : BookingDate::where('booking_id', $booking->id)->sum('amount'));
                $totalPaidAmount = (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('amount');
                if ($booking->payment_status === 'Paid') {
                    $balanceAmount = 0.00;
                    if ($totalPaidAmount < $totalBookingAmount) {
                        $totalPaidAmount = $totalBookingAmount;
                    }
                } else {
                    $balanceAmount = max(0.00, $totalBookingAmount - $totalPaidAmount);
                }
            } else {
                $balanceAmount = 0.00;
            }

            // Date-specific payment metrics
            $datePaidAmount = (float) $bDate->payments()->where('status', 'Success')->sum('amount');
            if ($bDate->payment_status === 'Paid' || ($booking && $booking->payment_status === 'Paid')) {
                $dateBalanceAmount = 0.00;
                if ($datePaidAmount < (float)$bDate->amount) {
                    $datePaidAmount = (float)$bDate->amount;
                }
            } else {
                $dateBalanceAmount = max(0.00, (float)$bDate->amount - $datePaidAmount);
            }

            // Calculate cancellation breakup (prospective for active, historical for cancelled)
            $isCancelled = ($bDate->status === 'Cancelled');
            $latestCancel = $isCancelled ? $bDate->bookingCancellations->sortByDesc('id')->first() : null;

            if ($isCancelled && $latestCancel) {
                $cBreakup = $latestCancel->deductions_breakup;
                $cancellationBreakup = [
                    'gross_paid' => (float)($cBreakup['gross'] ?? $datePaidAmount),
                    'turf_cancellation_fee' => (float)($cBreakup['turf_cancellation_fee'] ?? 0),
                    'turf_cancellation_fee_base' => (float)($cBreakup['turf_fee_base'] ?? ($cBreakup['turf_cancellation_fee'] ?? 0)),
                    'turf_cancellation_fee_gst' => (float)($cBreakup['turf_fee_gst'] ?? 0),
                    'turf_cancellation_fee_cgst' => (float)($cBreakup['turf_fee_cgst'] ?? 0),
                    'turf_cancellation_fee_sgst' => (float)($cBreakup['turf_fee_sgst'] ?? 0),
                    'platform_fee_retained' => (float)($cBreakup['platform_fee_retained'] ?? 0),
                    'saas_cancellation_fee' => (float)($cBreakup['saas_cancellation_fee'] ?? 0),
                    'saas_cancellation_fee_base' => (float)($cBreakup['saas_fee_base'] ?? ($cBreakup['saas_cancellation_fee'] ?? 0)),
                    'saas_cancellation_fee_gst' => (float)($cBreakup['saas_fee_gst'] ?? 0),
                    'saas_cancellation_fee_cgst' => (float)($cBreakup['saas_fee_cgst'] ?? 0),
                    'saas_cancellation_fee_sgst' => (float)($cBreakup['saas_fee_sgst'] ?? 0),
                    'saas_cancellation_fee_igst' => (float)($cBreakup['saas_fee_igst'] ?? 0),
                    'total_deductions' => (float)($cBreakup['total'] ?? $bDate->cancellation_fee_applied),
                    'refund_amount' => (float)($cBreakup['refund'] ?? $bDate->refund_amount),
                ];
            } elseif ($isCancelled) {
                $cancellationBreakup = [
                    'gross_paid' => (float)$datePaidAmount,
                    'turf_cancellation_fee' => (float)$bDate->cancellation_fee_applied,
                    'turf_cancellation_fee_base' => (float)$bDate->cancellation_fee_applied,
                    'turf_cancellation_fee_gst' => 0.00,
                    'turf_cancellation_fee_cgst' => 0.00,
                    'turf_cancellation_fee_sgst' => 0.00,
                    'platform_fee_retained' => 0.00,
                    'saas_cancellation_fee' => 0.00,
                    'saas_cancellation_fee_base' => 0.00,
                    'saas_cancellation_fee_gst' => 0.00,
                    'saas_cancellation_fee_cgst' => 0.00,
                    'saas_cancellation_fee_sgst' => 0.00,
                    'saas_cancellation_fee_igst' => 0.00,
                    'total_deductions' => (float)$bDate->cancellation_fee_applied,
                    'refund_amount' => (float)$bDate->refund_amount,
                ];
            } else {
                $allActiveDates = $booking ? $booking->bookingDates->where('status', '!=', 'Cancelled') : collect([$bDate]);
                $allActiveDatesCount = max(1, $allActiveDates->count());
                $totalBookingPlatformFee = (float)($booking?->platform_fee ?? 0) + (float)($booking?->platform_fee_gst ?? 0);
                $datePlatformFee = round($totalBookingPlatformFee / $allActiveDatesCount, 2);
                $datePlatformFee = min($datePaidAmount, $datePlatformFee);
                $slotCount = $bDate->bookingSlots ? $bDate->bookingSlots->count() : 1;
                $feeBreakdown = (new \App\Services\CancellationFeeCalculator())->calculate($booking->turf ?? $bDate->booking->turf, $datePaidAmount, $datePlatformFee, $slotCount);

                $cancellationBreakup = [
                    'gross_paid' => (float)$datePaidAmount,
                    'turf_cancellation_fee' => (float)$feeBreakdown['turf_fee'],
                    'turf_cancellation_fee_base' => (float)$feeBreakdown['turf_fee_base'],
                    'turf_cancellation_fee_gst' => (float)$feeBreakdown['turf_fee_gst'],
                    'turf_cancellation_fee_cgst' => (float)$feeBreakdown['turf_fee_cgst'],
                    'turf_cancellation_fee_sgst' => (float)$feeBreakdown['turf_fee_sgst'],
                    'platform_fee_retained' => (float)$datePlatformFee,
                    'saas_cancellation_fee' => (float)$feeBreakdown['saas_fee'],
                    'saas_cancellation_fee_base' => (float)$feeBreakdown['saas_fee_base'],
                    'saas_cancellation_fee_gst' => (float)$feeBreakdown['saas_fee_gst'],
                    'saas_cancellation_fee_cgst' => (float)$feeBreakdown['saas_fee_cgst'],
                    'saas_cancellation_fee_sgst' => (float)$feeBreakdown['saas_fee_sgst'],
                    'saas_cancellation_fee_igst' => (float)$feeBreakdown['saas_fee_igst'],
                    'total_deductions' => (float)$feeBreakdown['total_deductions'],
                    'refund_amount' => (float)$feeBreakdown['refund_amount'],
                ];
            }

            $activeDates = $booking ? $booking->bookingDates->where('status', '!=', 'Cancelled') : collect([$bDate]);
            $activeDatesCount = $activeDates->count();

            $allDatesBreakup = null;
            if ($activeDatesCount > 1) {
                $totGross = 0.00;
                $totTurf = 0.00;
                $totTurfBase = 0.00;
                $totTurfGst = 0.00;
                $totTurfCgst = 0.00;
                $totTurfSgst = 0.00;
                $totPlat = 0.00;
                $totSaas = 0.00;
                $totSaasBase = 0.00;
                $totSaasGst = 0.00;
                $totSaasCgst = 0.00;
                $totSaasSgst = 0.00;
                $totSaasIgst = 0.00;
                $totDed = 0.00;
                $totRef = 0.00;

                $totalBookingPlatformFee = (float)($booking?->platform_fee ?? 0) + (float)($booking?->platform_fee_gst ?? 0);
                $cancelFeeCalc = new \App\Services\CancellationFeeCalculator();
                $turfForCalc = $booking?->turf ?? $bDate->booking?->turf;

                foreach ($activeDates as $actD) {
                    $actPaid = (float)$actD->payments()->where('status', 'Success')->sum('amount');
                    if ($actD->payment_status === 'Paid' || ($booking && $booking->payment_status === 'Paid')) {
                        if ($actPaid < (float)$actD->amount) {
                            $actPaid = (float)$actD->amount;
                        }
                    }
                    $actPlatFee = min($actPaid, round($totalBookingPlatformFee / $activeDatesCount, 2));
                    $actSlots = $actD->bookingSlots ? $actD->bookingSlots->count() : 1;

                    $feeRes = $cancelFeeCalc->calculate($turfForCalc, $actPaid, $actPlatFee, $actSlots);

                    $totGross += $actPaid;
                    $totTurf += $feeRes['turf_fee'];
                    $totTurfBase += $feeRes['turf_fee_base'];
                    $totTurfGst += $feeRes['turf_fee_gst'];
                    $totTurfCgst += $feeRes['turf_fee_cgst'];
                    $totTurfSgst += $feeRes['turf_fee_sgst'];
                    $totPlat += $actPlatFee;
                    $totSaas += $feeRes['saas_fee'];
                    $totSaasBase += $feeRes['saas_fee_base'];
                    $totSaasGst += $feeRes['saas_fee_gst'];
                    $totSaasCgst += $feeRes['saas_fee_cgst'];
                    $totSaasSgst += $feeRes['saas_fee_sgst'];
                    $totSaasIgst += $feeRes['saas_fee_igst'];
                    $totDed += $feeRes['total_deductions'];
                    $totRef += $feeRes['refund_amount'];
                }

                $allDatesBreakup = [
                    'gross_paid' => round($totGross, 2),
                    'turf_cancellation_fee' => round($totTurf, 2),
                    'turf_cancellation_fee_base' => round($totTurfBase, 2),
                    'turf_cancellation_fee_gst' => round($totTurfGst, 2),
                    'turf_cancellation_fee_cgst' => round($totTurfCgst, 2),
                    'turf_cancellation_fee_sgst' => round($totTurfSgst, 2),
                    'platform_fee_retained' => round($totPlat, 2),
                    'saas_cancellation_fee' => round($totSaas, 2),
                    'saas_cancellation_fee_base' => round($totSaasBase, 2),
                    'saas_cancellation_fee_gst' => round($totSaasGst, 2),
                    'saas_cancellation_fee_cgst' => round($totSaasCgst, 2),
                    'saas_cancellation_fee_sgst' => round($totSaasSgst, 2),
                    'saas_cancellation_fee_igst' => round($totSaasIgst, 2),
                    'total_deductions' => round($totDed, 2),
                    'refund_amount' => round($totRef, 2),
                ];
            } else {
                $allDatesBreakup = $cancellationBreakup;
            }

            return [
                'id' => $bDate->id,
                'booking_id' => $booking->id ?? null,
                'user_id' => $booking->user_id ?? null,
                'turf_id' => $booking->turf_id ?? null,
                'turf_name' => $booking->turf->name ?? 'Unknown Turf',
                'booking_date' => Carbon::parse($bDate->booking_date)->format('F d, Y'),
                'date_raw' => $bDate->booking_date,
                'date_of_booking' => $booking ? Carbon::parse($booking->date_of_booking)->format('F d, Y h:i A') : 'N/A',
                'booking_type' => $booking->booking_type ?? 'N/A',
                'status' => $bDate->status ?? ($booking->status ?? 'Confirmed'),
                'booking_status' => $booking->status ?? 'Confirmed',
                'date_status' => $bDate->status ?? 'Confirmed',
                'payment_status' => $booking->payment_status ?? 'Pending',
                'date_payment_status' => $bDate->payment_status ?? 'Unpaid',
                'amount' => (float)$bDate->amount,
                'price' => '₹' . number_format($bDate->amount, 2),
                'summary_text' => $summaryText,
                'slots' => $slots,
                
                'total_booking_amount' => $totalBookingAmount,
                'total_paid_amount' => $totalPaidAmount,
                'balance_amount' => $balanceAmount,
                'date_paid_amount' => $datePaidAmount,
                'date_balance_amount' => $dateBalanceAmount,
                'booking_number' => $booking->booking_number ?? ('#' . ($booking->id ?? '')),
                'actual_amount' => (float)($bDate->actual_amount > 0 ? $bDate->actual_amount : ($booking->actual_amount ?? ($bDate->amount))),
                'booking_actual_amount' => (float)($booking->actual_amount ?? 0),
                'coupon_discount' => (float)($booking->coupon_discount ?? 0),
                'additional_discount' => (float)($booking->additional_discount ?? 0),
                'taxable_amount' => (float)($booking->taxable_amount ?? 0),
                'turf_gst_rate' => (float)($booking->turf_gst_rate ?? 0),
                'turf_gst_type' => $booking->turf->gst_type ?? 'exempt',
                'turf_gst_amount' => (float)($booking->turf_gst_amount ?? 0),
                'platform_fee' => (float)($booking->platform_fee ?? 0),
                'platform_fee_gst' => (float)($booking->platform_fee_gst ?? 0),
                'customer_gstin' => $booking->customer_gstin ?? null,
                'customer_company_name' => $booking->customer_company_name ?? null,
                
                'customer_name' => $booking->user->name ?? 'N/A',
                'customer_email' => $booking->user->email ?? 'N/A',
                'customer_mobile' => $booking->user->mobile ?? 'N/A',
                'share_message_template' => $booking->turf->share_message_template ?? null,
                'is_cancellation_active' => $booking->turf ? (bool)$booking->turf->is_cancellation_active : false,
                'cancellation_hours' => $booking->turf ? (int)$booking->turf->cancellation_hours : 0,
                'cancellation_fee' => $booking->turf ? (float)$booking->turf->cancellation_fee : 0.00,
                'cancellation_fee_percentage' => (float)(\App\Models\SaasSetting::first()?->cancellation_fee_percentage ?? 5.00),
                'active_dates_count' => $activeDatesCount,
                'cancellation_breakup' => $cancellationBreakup,
                'all_dates_cancellation_breakup' => $allDatesBreakup,
                'cancelled_at' => ($bDate->status === 'Cancelled' && $bDate->cancelled_at) ? Carbon::parse($bDate->cancelled_at)->format('F d, Y h:i A') : ($booking->cancelled_at ? Carbon::parse($booking->cancelled_at)->format('F d, Y h:i A') : null),
                'cancellation_fee_applied' => ($bDate->status === 'Cancelled') ? (float)$bDate->cancellation_fee_applied : (float)$booking->cancellation_fee_applied,
                'refund_amount' => ($bDate->status === 'Cancelled') ? (float)$bDate->refund_amount : (float)$booking->refund_amount,
                'refund_status' => ($bDate->status === 'Cancelled') ? ($bDate->refund_status ?? 'None') : ($booking->refund_status ?? 'None'),
                'refund_method' => ($bDate->status === 'Cancelled') ? (
                    $bDate->bookingCancellations->sortByDesc('id')->first()?->disbursement_channel === 'online_gateway'
                        ? 'razorpay'
                        : ($bDate->bookingCancellations->sortByDesc('id')->first()?->disbursement_channel === 'offline'
                            ? 'offline'
                            : ($bDate->refund_status === 'Cash / Offline Refund' ? 'offline' : ($bDate->refund_method ?? 'None')))
                ) : ($booking->refund_method ?? 'None'),
                'disbursement_channel' => ($bDate->status === 'Cancelled') ? $bDate->bookingCancellations->sortByDesc('id')->first()?->disbursement_channel : null,
                'razorpay_refund_id' => ($bDate->status === 'Cancelled') ? $bDate->bookingCancellations->sortByDesc('id')->first()?->razorpay_refund_id : null,
                'offline_reference' => ($bDate->status === 'Cancelled') ? $bDate->bookingCancellations->sortByDesc('id')->first()?->offline_reference : null,
                'refunded_at' => ($bDate->status === 'Cancelled' && $bDate->refunded_at) ? Carbon::parse($bDate->refunded_at)->format('F d, Y h:i A') : ($booking->refunded_at ? Carbon::parse($booking->refunded_at)->format('F d, Y h:i A') : null),
                'payments' => $bDate->payments()->where('status', 'Success')->get()->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_method' => $payment->payment_method,
                        'amount' => (float)$payment->amount,
                        'paid_at' => $payment->paid_at ? Carbon::parse($payment->paid_at)->format('F d, Y h:i A') : 'N/A',
                        'refunded_amount' => (float)$payment->refunded_amount,
                        'refund_status' => $payment->refund_status ?? 'None',
                        'refunded_at' => $payment->refunded_at ? Carbon::parse($payment->refunded_at)->format('F d, Y h:i A') : null,
                    ];
                }),
            ];
        });

        $dataArr = $formatted->toArray();
        usort($dataArr['data'], function ($a, $b) {
            $slotsA = $a['slots'] ?? [];
            $slotsB = $b['slots'] ?? [];
            $timeA = !empty($slotsA) ? ($slotsA[0]['from_time'] ?? '') : '';
            $timeB = !empty($slotsB) ? ($slotsB[0]['from_time'] ?? '') : '';
            return strcmp($timeA, $timeB);
        });

        return response()->json($dataArr);
    }

    /**
     * Get available and occupied slots for a turf on a specific date.
     */
    public function getSlots(Request $request, Turf $turf): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'dates' => 'nullable|array',
            'dates.*' => 'date_format:Y-m-d',
        ]);

        if (!$request->has('date') && !$request->has('dates')) {
            return response()->json(['message' => 'The date or dates field is required.'], 422);
        }

        $dates = $request->input('dates');
        if (!is_array($dates)) {
            $dates = [$request->input('date')];
        }
        $firstDate = Carbon::parse($dates[0]);
        $dayOfWeek = strtolower($firstDate->format('D'));

        $occupiedSlotIds = \App\Models\BookingSlot::where('status', '!=', 'cancelled')
            ->whereHas('bookingDate', function ($q) use ($turf, $dates) {
                $q->whereIn('booking_date', $dates)
                  ->where('status', '!=', 'Cancelled')
                  ->whereHas('booking', function ($bq) use ($turf) {
                      $bq->where('turf_id', $turf->id)
                         ->where('status', 'Confirmed');
                  });
            })
            ->pluck('slot_id')
            ->toArray();

        $slotLocks = \App\Models\SlotLock::where('turf_id', $turf->id)
            ->whereIn('lock_date', $dates)
            ->get();
        $lockedSlotMap = [];
        foreach ($slotLocks as $lock) {
            $lockedSlotMap[$lock->slot_id] = $lock->reason ?: 'Locked for maintenance';
        }

        // Get pricing wizard details helper
        $wizard = is_array($turf->pricing_wizard_data) 
            ? $turf->pricing_wizard_data 
            : json_decode($turf->pricing_wizard_data, true);

        $getRateForTime = function ($wizard, $day, $time) {
            if (!$wizard) return null;
            $sameWeek = $wizard['sameRateThroughoutWeek'] ?? 'yes';
            if ($sameWeek === 'yes') {
                $sameDay = $wizard['sameRateThroughoutDayAll'] ?? 'yes';
                if ($sameDay === 'yes') {
                    return isset($wizard['flatRateAll']) && $wizard['flatRateAll'] !== '' ? (float)$wizard['flatRateAll'] : null;
                } else {
                    $ranges = $wizard['timeRangesAll'] ?? [];
                    foreach ($ranges as $range) {
                        $from = date('H:i', strtotime($range['from'] ?? '00:00'));
                        $to = date('H:i', strtotime($range['to'] ?? '23:59'));
                        if ($from > $to) {
                            if ($time >= $from || $time < $to) {
                                return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                            }
                        } else {
                            if ($time >= $from && $time < $to) {
                                return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                            }
                        }
                    }
                }
            } else {
                $dayGroups = $wizard['dayGroups'] ?? [];
                foreach ($dayGroups as $group) {
                    $days = array_map('strtolower', $group['days'] ?? []);
                    if (in_array($day, $days)) {
                        $sameDay = $group['sameRateThroughoutDay'] ?? 'yes';
                        if ($sameDay === 'yes') {
                            return isset($group['flatRate']) && $group['flatRate'] !== '' ? (float)$group['flatRate'] : null;
                        } else {
                            $ranges = $group['timeRanges'] ?? [];
                            foreach ($ranges as $range) {
                                $from = date('H:i', strtotime($range['from'] ?? '00:00'));
                                $to = date('H:i', strtotime($range['to'] ?? '23:59'));
                                if ($from > $to) {
                                    if ($time >= $from || $time < $to) {
                                        return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                                    }
                                } else {
                                    if ($time >= $from && $time < $to) {
                                        return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return null;
        };

        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $isTodaySelected = in_array($today, $dates);
        $nowTime = Carbon::now('Asia/Kolkata')->toTimeString();

        // Format all turf slots
        $slots = $turf->slots()
            ->with('category')
            ->wherePivot('is_active', true)
            ->get()
            ->map(function ($slot) use ($dayOfWeek, $occupiedSlotIds, $wizard, $dates, $today, $nowTime, $lockedSlotMap) {
                // Determine slot price
                $fromTime24 = date('H:i', strtotime($slot->from_time));
                $hourlyRate = $this->getRateForTime($wizard, $dayOfWeek, $fromTime24);
                $duration = intval($slot->duration ?: 30);

                if ($hourlyRate !== null) {
                    $price = round(($hourlyRate / 60) * $duration, 2);
                } else {
                    if (isset($slot->pivot->$dayOfWeek)) {
                        $price = (float)$slot->pivot->$dayOfWeek;
                    } else {
                        $price = round((1000.00 / 60) * $duration, 2);
                    }
                }

                // Format time to 12 hour AM/PM
                $fromFormatted = date('h:i A', strtotime($slot->from_time));
                $toFormatted = date('h:i A', strtotime($slot->to_time));

                $isPast = false;
                foreach ($dates as $d) {
                    if ($d < $today || ($d === $today && $slot->from_time < $nowTime)) {
                        $isPast = true;
                        break;
                    }
                }
                $isLocked = isset($lockedSlotMap[$slot->id]);
                $lockReason = $lockedSlotMap[$slot->id] ?? null;

                return [
                    'id' => $slot->id,
                    'from_time' => $slot->from_time,
                    'to_time' => $slot->to_time,
                    'time_label' => "$fromFormatted - $toFormatted",
                    'price' => $price,
                    'is_booked' => in_array($slot->id, $occupiedSlotIds) || $isPast || $isLocked,
                    'is_locked' => $isLocked,
                    'lock_reason' => $lockReason,
                    'category' => $slot->category?->name ?? 'Other',
                ];
            })
            ->sortBy('from_time')
            ->values();

        return response()->json($slots);
    }

    /**
     * Verify a coupon code for booking.
     */
    public function verifyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'turf_id' => 'required|exists:turfs,id',
            'slot_count' => 'required|integer|min:1',
            'booking_dates' => 'required|array',
            'booking_dates.*' => 'required|date_format:Y-m-d',
        ]);

        $code = $validated['code'];
        $turfId = $validated['turf_id'];
        $slotCount = $validated['slot_count'];
        $dates = $validated['booking_dates'];
        $userId = auth()->id();

        // Find Coupon
        $coupon = \App\Models\Coupon::where('turf_id', $turfId)
            ->where('code', $code)
            ->first();

        if (!$coupon) {
            return response()->json([
                'message' => 'Invalid coupon code for this turf.',
            ], 422);
        }

        if (!$coupon->is_active) {
            return response()->json([
                'message' => 'This coupon is no longer active.',
            ], 422);
        }

        $today = Carbon::today('Asia/Kolkata');
        if ($coupon->starts_at && Carbon::parse($coupon->starts_at)->gt($today)) {
            return response()->json([
                'message' => 'This coupon is not yet active.',
            ], 422);
        }
        if ($coupon->expires_at && Carbon::parse($coupon->expires_at)->lt($today)) {
            return response()->json([
                'message' => 'This coupon has expired.',
            ], 422);
        }

        if ($slotCount < $coupon->minimum_slots_to_be_ordered) {
            return response()->json([
                'message' => "This coupon requires a minimum of {$coupon->minimum_slots_to_be_ordered} slots to be ordered.",
            ], 422);
        }

        foreach ($dates as $dateStr) {
            $date = Carbon::parse($dateStr);
            $dayName = strtolower($date->format('D'));
            if (!$coupon->$dayName) {
                $dayLabel = ucfirst($dayName);
                return response()->json([
                    'message' => "This coupon is not valid on {$dayLabel}.",
                ], 422);
            }
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json([
                'message' => 'This coupon usage limit has been reached.',
            ], 422);
        }

        if ($coupon->usage_limit_per_user !== null) {
            $userUsageCount = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();
            if ($userUsageCount >= $coupon->usage_limit_per_user) {
                return response()->json([
                    'message' => 'You have reached the usage limit for this coupon.',
                ], 422);
            }
        }

        return response()->json([
            'message' => 'Coupon verified successfully!',
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float)$coupon->discount_value,
                'max_discount_amount' => $coupon->max_discount_amount !== null ? (float)$coupon->max_discount_amount : null,
            ]
        ]);
    }

    /**
     * Book one or more slots/dates for a turf.
     */
    public function store(Request $request, Turf $turf): JsonResponse
    {
        $validated = $request->validate([
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'required|exists:slots,id',
            'booking_dates' => 'required|array',
            'booking_dates.*' => 'required|date_format:Y-m-d',
            'booking_type' => 'required|string|in:day,long,scattered',
            'coupons' => 'nullable|array', // key: date (YYYY-MM-DD), value: coupon code (string)
            'additional_discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:offline,App,Cash,UPI,Other',
            'payment_option' => 'nullable|string|in:full,part',
            'amount_received' => 'nullable|numeric|min:0', // for manager
            'customer_id' => 'nullable|exists:users,id', // for manager
            'razorpay_payment_id' => 'nullable|string',
            'razorpay_order_id' => 'nullable|string',
            'razorpay_signature' => 'nullable|string',
            'customer_gstin' => 'nullable|string|max:15',
            'customer_company_name' => 'nullable|string|max:150',
        ]);

        $userId = auth()->id();
        $isStaffOrAdmin = auth()->user()->hasAnyRole(['saas-admin', 'turf-admin', 'manager']);
        $customerId = $validated['customer_id'] ?? null;
        
        $targetUserId = ($isStaffOrAdmin && $customerId) ? $customerId : $userId;

        $slotIds = $validated['slot_ids'];
        $dates = $validated['booking_dates'];
        $bookingType = $validated['booking_type'];
        $dateCoupons = $validated['coupons'] ?? [];
        $paymentMethod = $validated['payment_method'];
        $paymentOption = $validated['payment_option'] ?? 'full';

        $pricingCalculator = new BookingPricingCalculator();

        // 1. Evaluate Pre-Booking Guardrails
        $guardrailCheck = $pricingCalculator->validateGuardrails(
            $turf,
            auth()->user(),
            $dates,
            $slotIds,
            $paymentMethod,
            $paymentOption
        );
        if (!$guardrailCheck['valid']) {
            return response()->json([
                'message' => $guardrailCheck['message'],
            ], $guardrailCheck['status_code']);
        }

        // DEBT GUARDRAIL CHECK FOR OFFLINE PAYMENTS IN STORE
        if (in_array($paymentMethod, ['Cash', 'UPI', 'Other', 'offline'])) {
            $lockError = $this->checkOfflinePaymentDebtLock($turf);
            if ($lockError) {
                return response()->json(['message' => $lockError], 422);
            }
        }

        $manualDiscount = ($isStaffOrAdmin && isset($validated['additional_discount'])) ? (float)$validated['additional_discount'] : 0.00;

        $settings = \App\Models\SaasSetting::first();
        $minSlots = $settings?->min_slots_booking ?? 2;

        $allActiveSlots = $turf->slots()
            ->wherePivot('is_active', true)
            ->orderBy('from_time')
            ->pluck('slots.id')
            ->toArray();

        $indices = [];
        foreach ($slotIds as $id) {
            $idx = array_search($id, $allActiveSlots);
            if ($idx === false) {
                return response()->json([
                    'message' => "Invalid slot selection.",
                ], 422);
            }
            $indices[] = $idx;
        }

        sort($indices);

        $segments = [];
        $currentSegment = [$indices[0]];

        for ($i = 1; $i < count($indices); $i++) {
            if ($indices[$i] === $indices[$i - 1] + 1) {
                $currentSegment[] = $indices[$i];
            } else {
                $segments[] = $currentSegment;
                $currentSegment = [$indices[$i]];
            }
        }
        $segments[] = $currentSegment;

        foreach ($segments as $segment) {
            if (count($segment) < $minSlots) {
                return response()->json([
                    'message' => "Each consecutive block of selected slots must contain at least {$minSlots} slots.",
                ], 422);
            }
        }

        // Get pricing wizard details helper
        $wizard = is_array($turf->pricing_wizard_data) 
            ? $turf->pricing_wizard_data 
            : json_decode($turf->pricing_wizard_data, true);

        // We will perform a transaction to ensure atomic bookings with lockForUpdate concurrency protection
        \DB::beginTransaction();

        try {
            $totalSubtotal = 0.00;
            $totalCouponDiscount = 0.00;
            $calculatedDates = [];

            foreach ($dates as $dateStr) {
                $dateObj = Carbon::parse($dateStr);
                $dayOfWeek = strtolower($dateObj->format('D'));
                $dateSubtotal = 0.00;
                $slotsToCreate = [];

                foreach ($slotIds as $slotId) {
                    $isLocked = \App\Models\SlotLock::where('turf_id', $turf->id)
                        ->where('slot_id', $slotId)
                        ->where('lock_date', $dateStr)
                        ->lockForUpdate()
                        ->first();

                    if ($isLocked) {
                        if ($bookingType === 'day') {
                            \DB::rollBack();
                            $reason = $isLocked->reason ? " ({$isLocked->reason})" : "";
                            return response()->json([
                                'message' => "Selected slot is locked for date: $dateStr{$reason}.",
                            ], 422);
                        }
                        continue;
                    }

                    $alreadyBooked = \App\Models\BookingSlot::where('slot_id', $slotId)
                        ->where('status', '!=', 'cancelled')
                        ->whereHas('bookingDate', function ($q) use ($turf, $dateStr) {
                            $q->where('booking_date', $dateStr)
                              ->where('status', '!=', 'Cancelled')
                              ->whereHas('booking', function ($bq) use ($turf) {
                                  $bq->where('turf_id', $turf->id)
                                     ->where('status', 'Confirmed');
                              });
                        })
                        ->lockForUpdate()
                        ->exists();

                    if ($alreadyBooked) {
                        if ($bookingType === 'day') {
                            \DB::rollBack();
                            return response()->json([
                                'message' => "Slot is already booked for date: $dateStr.",
                            ], 422);
                        }
                        continue;
                    }

                    // Get the slot model to calculate price
                    $slot = $turf->slots()->where('slots.id', $slotId)->first();
                    if (!$slot) {
                        \DB::rollBack();
                        return response()->json([
                            'message' => "Invalid slot ID for this turf.",
                        ], 422);
                    }

                    // Block past slots for today's booking date
                    $todayStr = Carbon::today('Asia/Kolkata')->toDateString();
                    if ($dateStr === $todayStr) {
                        $nowTime = Carbon::now('Asia/Kolkata')->toTimeString();
                        if ($slot->from_time < $nowTime) {
                            \DB::rollBack();
                            return response()->json([
                                'message' => "Cannot book a past slot.",
                            ], 422);
                        }
                    }

                    // Price calculation logic
                    $fromTime24 = date('H:i', strtotime($slot->from_time));
                    $hourlyRate = $this->getRateForTime($wizard, $dayOfWeek, $fromTime24);
                    $duration = intval($slot->duration ?: 30);

                    if ($hourlyRate !== null) {
                        $price = round(($hourlyRate / 60) * $duration, 2);
                    } else {
                        if (isset($slot->pivot->$dayOfWeek)) {
                            $price = (float)$slot->pivot->$dayOfWeek;
                        } else {
                            $price = round((1000.00 / 60) * $duration, 2);
                        }
                    }

                    $dateSubtotal += $price;
                    $slotsToCreate[] = $slotId;
                }

                // Verify and Apply Coupon for this specific date if provided
                $couponCode = $dateCoupons[$dateStr] ?? null;
                $coupon = null;
                $couponDiscount = 0.00;

                if ($couponCode) {
                    $coupon = \App\Models\Coupon::where('turf_id', $turf->id)
                        ->where('code', $couponCode)
                        ->first();

                    if (!$coupon || !$coupon->is_active) {
                        \DB::rollBack();
                        return response()->json([
                            'message' => "Invalid or inactive coupon code '{$couponCode}' for date {$dateStr}.",
                        ], 422);
                    }

                    $today = Carbon::today('Asia/Kolkata');
                    if ($coupon->starts_at && Carbon::parse($coupon->starts_at)->gt($today)) {
                        \DB::rollBack();
                        return response()->json(['message' => "Coupon '{$couponCode}' is not active yet."], 422);
                    }
                    if ($coupon->expires_at && Carbon::parse($coupon->expires_at)->lt($today)) {
                        \DB::rollBack();
                        return response()->json(['message' => "Coupon '{$couponCode}' has expired."], 422);
                    }
                    if (!$coupon->$dayOfWeek) {
                        \DB::rollBack();
                        return response()->json(['message' => "Coupon '{$couponCode}' is not valid on " . ucfirst($dayOfWeek) . "."], 422);
                    }
                    
                    if (count($slotsToCreate) < $coupon->minimum_slots_to_be_ordered) {
                        \DB::rollBack();
                        return response()->json(['message' => "Coupon '{$couponCode}' requires at least {$coupon->minimum_slots_to_be_ordered} available slots on {$dateStr}."], 422);
                    }

                    $userUsageCount = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
                        ->where('user_id', $targetUserId)
                        ->count();

                    if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                        \DB::rollBack();
                        return response()->json(['message' => "Coupon '{$couponCode}' overall usage limit reached."], 422);
                    }
                    if ($coupon->usage_limit_per_user !== null && $userUsageCount >= $coupon->usage_limit_per_user) {
                        \DB::rollBack();
                        return response()->json(['message' => "User usage limit for coupon '{$couponCode}' reached."], 422);
                    }

                    // Calculate discount
                    if ($coupon->discount_type === 'fixed') {
                        $couponDiscount = (float)$coupon->discount_value;
                    } else {
                        $couponDiscount = $dateSubtotal * ($coupon->discount_value / 100);
                    }

                    if ($coupon->max_discount_amount !== null && $couponDiscount > $coupon->max_discount_amount) {
                        $couponDiscount = (float)$coupon->max_discount_amount;
                    }

                    $couponDiscount = min($couponDiscount, $dateSubtotal);
                }

                $totalSubtotal += $dateSubtotal;
                $totalCouponDiscount += $couponDiscount;

                $calculatedDates[] = [
                    'date' => $dateStr,
                    'day_name' => ucfirst($dayOfWeek),
                    'subtotal' => $dateSubtotal,
                    'coupon_discount' => $couponDiscount,
                    'after_coupon' => max(0.00, $dateSubtotal - $couponDiscount),
                    'slots' => $slotsToCreate,
                    'coupon' => $coupon,
                ];
            }

            // Distribute manual additional discount proportionally across dates
            $sumAfterCoupon = array_sum(array_column($calculatedDates, 'after_coupon'));
            $dateCount = count($calculatedDates);

            foreach ($calculatedDates as &$calcDate) {
                if ($manualDiscount > 0) {
                    if ($sumAfterCoupon > 0) {
                        $dateAddDiscount = round($manualDiscount * ($calcDate['after_coupon'] / $sumAfterCoupon), 2);
                    } else {
                        $dateAddDiscount = round($manualDiscount / $dateCount, 2);
                    }
                } else {
                    $dateAddDiscount = 0.00;
                }
                $calcDate['additional_discount'] = min($dateAddDiscount, $calcDate['after_coupon']);
            }
            unset($calcDate);

            // Execute full multi-tier financial, tax, commission, and cancellation calculations
            $pricing = $pricingCalculator->calculatePricing(
                $turf,
                $calculatedDates,
                $manualDiscount,
                $paymentMethod,
                $paymentOption
            );

            // Create parent booking record with full financial snapshot
            $booking = Booking::create([
                'user_id' => $targetUserId,
                'turf_id' => $turf->id,
                'date_of_booking' => Carbon::now(),
                'booking_type' => $bookingType,
                'status' => 'Confirmed',
                'payment_status' => 'Pending',
                'actual_amount' => $pricing['actual_amount'] ?? ($pricing['subtotal'] ?? 0.00),
                'coupon_discount' => $pricing['coupon_discount'],
                'additional_discount' => $pricing['additional_discount'],
                'taxable_amount' => $pricing['taxable_amount'],
                'turf_gst_amount' => $pricing['turf_gst_amount'],
                'turf_cgst_amount' => $pricing['turf_cgst_amount'],
                'turf_sgst_amount' => $pricing['turf_sgst_amount'],
                'turf_gst_rate' => $pricing['turf_gst_rate'],
                'turf_gst_type' => $pricing['turf_gst_type'],
                'platform_fee' => $pricing['platform_fee'],
                'platform_fee_gst' => $pricing['platform_fee_gst'],
                'platform_fee_cgst' => $pricing['platform_fee_cgst'],
                'platform_fee_sgst' => $pricing['platform_fee_sgst'],
                'platform_fee_igst' => $pricing['platform_fee_igst'],
                'total_amount' => $pricing['total_amount'],
                'payable_now' => $pricing['payable_now'],
                'balance_amount' => $pricing['balance_amount'],
                'is_part_payment' => $pricing['is_part_payment'],
                'customer_gstin' => $validated['customer_gstin'] ?? null,
                'customer_company_name' => $validated['customer_company_name'] ?? null,
                'gateway_charge_amount' => 0.00,
                'gateway_tax_amount' => 0.00,
                'commission_rate' => $pricing['commission_rate'],
                'commission_amount' => $pricing['commission_amount'],
                'commission_gst_amount' => $pricing['commission_gst_amount'],
                'commission_cgst_amount' => $pricing['commission_cgst_amount'],
                'commission_sgst_amount' => $pricing['commission_sgst_amount'],
                'commission_igst_amount' => $pricing['commission_igst_amount'],
                'turf_payout_amount' => $pricing['turf_payout_amount'],
                'is_cancellation_active' => $pricing['is_cancellation_active'],
                'cancellation_hours' => $pricing['cancellation_hours'],
                'cancellation_turf_fee' => $pricing['cancellation_turf_fee'],
                'cancellation_platform_fee_pct' => $pricing['cancellation_platform_fee_pct'],
                'estimated_refund_amount' => $pricing['estimated_refund_amount'],
            ]);

            // Save booking dates & slots
            $pricingDatesByDate = collect($pricing['dates'])->keyBy('date');

            foreach ($calculatedDates as $calcDate) {
                $pDate = $pricingDatesByDate->get($calcDate['date']) ?? [];

                $bookingDate = $booking->bookingDates()->create([
                    'booking_date' => $calcDate['date'],
                    'actual_amount' => $pDate['actual_amount'] ?? ($pDate['subtotal'] ?? ($calcDate['subtotal'] ?? 0.00)),
                    'amount' => $pDate['date_total'] ?? ($pDate['turf_total'] ?? $calcDate['after_coupon']),
                    'taxable_amount' => $pDate['taxable_amount'] ?? 0.00,
                    'turf_gst_amount' => $pDate['turf_gst_amount'] ?? 0.00,
                    'turf_cgst_amount' => $pDate['turf_cgst_amount'] ?? 0.00,
                    'turf_sgst_amount' => $pDate['turf_sgst_amount'] ?? 0.00,
                    'paid_amount' => 0.00,
                    'balance_amount' => $pDate['balance_amount'] ?? 0.00,
                    'commission_rate' => $pDate['commission_rate'] ?? 0.00,
                    'commission_amount' => $pDate['commission_amount'] ?? 0.00,
                    'commission_gst_amount' => $pDate['commission_gst_amount'] ?? 0.00,
                    'commission_cgst_amount' => $pDate['commission_cgst_amount'] ?? 0.00,
                    'commission_sgst_amount' => $pDate['commission_sgst_amount'] ?? 0.00,
                    'commission_igst_amount' => $pDate['commission_igst_amount'] ?? 0.00,
                    'turf_payout_amount' => $pDate['turf_payout_amount'] ?? 0.00,
                    'cash_held_amount' => $pDate['cash_held_amount'] ?? 0.00,
                    'cancellation_turf_fee' => $pDate['cancellation_turf_fee'] ?? 0.00,
                    'cancellation_platform_fee' => $pDate['cancellation_platform_fee'] ?? 0.00,
                    'estimated_refund_amount' => $pDate['estimated_refund_amount'] ?? 0.00,
                    'coupon_discount' => $calcDate['coupon_discount'],
                    'additional_discount' => $calcDate['additional_discount'],
                    'payment_status' => 'Unpaid',
                ]);

                foreach ($calcDate['slots'] as $slotId) {
                    $bookingDate->bookingSlots()->create([
                        'slot_id' => $slotId,
                        'status' => 'active',
                    ]);
                }

                if ($calcDate['coupon'] && $calcDate['coupon_discount'] > 0) {
                    \App\Models\CouponUsage::create([
                        'coupon_id' => $calcDate['coupon']->id,
                        'user_id' => $targetUserId,
                        'booking_date_id' => $bookingDate->id,
                        'discount_applied' => $calcDate['coupon_discount'],
                        'used_at' => Carbon::now(),
                    ]);
                    $calcDate['coupon']->increment('used_count');
                }
            }

            // Distribute paid amount and create payment records
            $gatewayCharge = 0.00;
            $gatewayTax = 0.00;
            $rzpPaymentId = $request->input('razorpay_payment_id');
            $rzpOrderId = $request->input('razorpay_order_id');
            $rzpSignature = $request->input('razorpay_signature');
            $rzpPayload = null;

            if ($isStaffOrAdmin && $customerId) {
                // Manager/Admin booking for customer:
                $amountReceived = (float)($validated['amount_received'] ?? 0.00);
                if ($amountReceived > 0) {
                    $this->distributePaymentToBooking($booking, $amountReceived, $paymentMethod, null, 0.00, 0.00);
                }
            } else {
                // Customer booking:
                if ($paymentMethod === 'App') {
                    $paidAmount = (float) $pricing['payable_now'];

                    if ($paidAmount > 0) {
                        if ($rzpPaymentId) {
                            $setting = \App\Models\SaasSetting::first();
                            $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
                            $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

                            if ($rzpKey && $rzpSecret) {
                                try {
                                    $fetch = \Illuminate\Support\Facades\Http::withBasicAuth($rzpKey, $rzpSecret)
                                        ->get("https://api.razorpay.com/v1/payments/{$rzpPaymentId}");
                                    if ($fetch->successful()) {
                                        $pData = $fetch->json();
                                        $rzpPayload = $pData;
                                        if (empty($rzpOrderId) && !empty($pData['order_id'])) {
                                            $rzpOrderId = $pData['order_id'];
                                        }
                                        if (($pData['status'] ?? '') === 'authorized') {
                                            $captureRes = \Illuminate\Support\Facades\Http::withBasicAuth($rzpKey, $rzpSecret)
                                                ->asForm()
                                                ->post("https://api.razorpay.com/v1/payments/{$rzpPaymentId}/capture", [
                                                    'amount' => $pData['amount'],
                                                    'currency' => $pData['currency'] ?? 'INR',
                                                ]);
                                            if ($captureRes->successful()) {
                                                $pData = $captureRes->json();
                                                $rzpPayload = $pData;
                                            }
                                        }
                                        if (isset($pData['fee'])) {
                                            $gatewayCharge = round((float)$pData['fee'] / 100, 2);
                                        }
                                        if (isset($pData['tax'])) {
                                            $gatewayTax = round((float)$pData['tax'] / 100, 2);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Razorpay auto-capture/fee fetch error on store: ' . $e->getMessage());
                                }
                            }
                        }

                        $this->distributePaymentToBooking(
                            $booking,
                            $paidAmount,
                            'App',
                            $rzpPaymentId,
                            $gatewayCharge,
                            $gatewayTax,
                            $rzpOrderId,
                            $rzpSignature,
                            $rzpPayload
                        );
                    }
                } elseif ($paymentMethod === 'offline' || $paymentOption === 'pay_at_location') {
                    // Customer chose Pay at Location:
                    // The customer will pay the turf owner directly at the venue (offline).
                    // Record payment with status 'Pending' so the booking remains Unpaid with full balance,
                    // while calculating SaaS commission & platform fee to debit from the turf owner's wallet upfront.
                    $this->distributePaymentToBooking(
                        $booking,
                        (float)$pricing['total_amount'],
                        'offline',
                        null,
                        0.00,
                        0.00,
                        null,
                        null,
                        null,
                        'Pending'
                    );
                }
            }

            $this->recalculateBookingPaymentStatus($booking);

            try {
                \App\Services\NotificationService::notifyBookingCreated($booking);
            } catch (\Throwable $ne) {
                \Illuminate\Support\Facades\Log::warning('Booking notification could not be sent: ' . $ne->getMessage());
            }

            \DB::commit();

            return response()->json([
                'message' => 'Turf booked successfully!',
                'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments.paymentGateway']),
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error occurred while booking: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'An error occurred while booking: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview booking calculations, pricing wizard, slots availability, and coupons.
     */
    public function preview(Request $request, Turf $turf): JsonResponse
    {
        $validated = $request->validate([
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'required|exists:slots,id',
            'booking_dates' => 'required|array',
            'booking_dates.*' => 'required|date_format:Y-m-d',
            'booking_type' => 'required|string|in:day,long,scattered',
            'coupons' => 'nullable|array', // key is date (YYYY-MM-DD), value is coupon code (string)
            'additional_discount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:offline,App,Cash,UPI,Other,razorpay',
            'payment_option' => 'nullable|string|in:full,part',
        ]);

        $isStaffOrAdmin = auth()->user()->hasAnyRole(['saas-admin', 'turf-admin', 'manager']);
        $slotIds = $validated['slot_ids'];
        $dates = $validated['booking_dates'];
        $bookingType = $validated['booking_type'];
        $dateCoupons = $validated['coupons'] ?? [];
        $paymentMethod = $validated['payment_method'] ?? 'App';
        $paymentOption = $validated['payment_option'] ?? 'full';
        $manualDiscount = ($isStaffOrAdmin && isset($validated['additional_discount'])) ? (float)$validated['additional_discount'] : 0.00;

        $pricingCalculator = new BookingPricingCalculator();

        // 1. Evaluate Pre-Booking Guardrails
        $guardrailCheck = $pricingCalculator->validateGuardrails(
            $turf,
            auth()->user(),
            $dates,
            $slotIds,
            $paymentMethod,
            $paymentOption
        );
        if (!$guardrailCheck['valid']) {
            return response()->json([
                'message' => $guardrailCheck['message'],
            ], $guardrailCheck['status_code']);
        }

        $wizard = is_array($turf->pricing_wizard_data) 
            ? $turf->pricing_wizard_data 
            : json_decode($turf->pricing_wizard_data, true);

        $totalSubtotal = 0.00;
        $totalCouponDiscount = 0.00;
        $calculatedDates = [];

        foreach ($dates as $dateStr) {
            $dateObj = Carbon::parse($dateStr);
            $dayOfWeek = strtolower($dateObj->format('D'));
            $dateSubtotal = 0.00;
            $slotsData = [];
            
            foreach ($slotIds as $slotId) {
                $alreadyBooked = \App\Models\BookingSlot::where('slot_id', $slotId)
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('bookingDate', function ($q) use ($turf, $dateStr) {
                        $q->where('booking_date', $dateStr)
                          ->where('status', '!=', 'Cancelled')
                          ->whereHas('booking', function ($bq) use ($turf) {
                              $bq->where('turf_id', $turf->id)
                                 ->where('status', 'Confirmed');
                          });
                    })
                    ->exists();

                $isLocked = \App\Models\SlotLock::where('turf_id', $turf->id)
                    ->where('slot_id', $slotId)
                    ->where('lock_date', $dateStr)
                    ->exists();

                $isUnavailable = $alreadyBooked || $isLocked;

                $slot = $turf->slots()->where('slots.id', $slotId)->first();
                if (!$slot) {
                    continue;
                }

                $fromTime24 = date('H:i', strtotime($slot->from_time));
                $hourlyRate = $this->getRateForTime($wizard, $dayOfWeek, $fromTime24);
                $duration = intval($slot->duration ?: 30);

                if ($hourlyRate !== null) {
                    $price = round(($hourlyRate / 60) * $duration, 2);
                } else {
                    if (isset($slot->pivot->$dayOfWeek)) {
                        $price = (float)$slot->pivot->$dayOfWeek;
                    } else {
                        $price = round((1000.00 / 60) * $duration, 2);
                    }
                }

                if (!$isUnavailable) {
                    $dateSubtotal += $price;
                }

                $slotsData[] = [
                    'id' => $slotId,
                    'time_label' => date('h:i A', strtotime($slot->from_time)) . ' - ' . date('h:i A', strtotime($slot->to_time)),
                    'price' => $price,
                    'status' => $isUnavailable ? 'booked' : 'available',
                ];
            }

            // Coupon calculations for this specific date
            $couponDiscount = 0.00;
            $couponApplied = false;
            $couponError = null;
            $couponCode = $dateCoupons[$dateStr] ?? null;

            if ($couponCode) {
                $coupon = \App\Models\Coupon::where('turf_id', $turf->id)
                    ->where('code', $couponCode)
                    ->first();

                if (!$coupon) {
                    $couponError = "Invalid coupon code.";
                } elseif (!$coupon->is_active) {
                    $couponError = "Coupon is inactive.";
                } else {
                    $today = Carbon::today('Asia/Kolkata');
                    if ($coupon->starts_at && Carbon::parse($coupon->starts_at)->gt($today)) {
                        $couponError = "Coupon is not yet active.";
                    } elseif ($coupon->expires_at && Carbon::parse($coupon->expires_at)->lt($today)) {
                        $couponError = "Coupon has expired.";
                    } elseif (!$coupon->$dayOfWeek) {
                        $couponError = "Coupon is not valid on " . ucfirst($dayOfWeek) . ".";
                    } else {
                        $availableSlotCount = 0;
                        foreach ($slotsData as $s) {
                            if ($s['status'] === 'available') {
                                $availableSlotCount++;
                            }
                        }

                        if ($availableSlotCount < $coupon->minimum_slots_to_be_ordered) {
                            $couponError = "Requires min {$coupon->minimum_slots_to_be_ordered} available slots on this date.";
                        } else {
                            $userUsageCount = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
                                ->where('user_id', auth()->id())
                                ->count();
                            
                            if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                                $couponError = "Coupon overall usage limit reached.";
                            } elseif ($coupon->usage_limit_per_user !== null && $userUsageCount >= $coupon->usage_limit_per_user) {
                                $couponError = "Your usage limit for this coupon is reached.";
                            } else {
                                if ($coupon->discount_type === 'fixed') {
                                    $couponDiscount = (float)$coupon->discount_value;
                                } else {
                                    $couponDiscount = $dateSubtotal * ($coupon->discount_value / 100);
                                }

                                if ($coupon->max_discount_amount !== null && $couponDiscount > $coupon->max_discount_amount) {
                                    $couponDiscount = (float)$coupon->max_discount_amount;
                                }

                                $couponDiscount = min($couponDiscount, $dateSubtotal);
                                $couponApplied = true;
                            }
                        }
                    }
                }
            }

            $totalSubtotal += $dateSubtotal;
            $totalCouponDiscount += $couponDiscount;

            $calculatedDates[] = [
                'date' => $dateStr,
                'day_name' => ucfirst($dayOfWeek),
                'subtotal' => $dateSubtotal,
                'coupon_discount' => $couponDiscount,
                'after_coupon' => max(0.00, $dateSubtotal - $couponDiscount),
                'slots' => $slotsData,
                'coupon' => [
                    'applied' => $couponApplied,
                    'code' => $couponCode,
                    'discount' => $couponDiscount,
                    'error' => $couponError,
                ]
            ];
        }

        // Distribute manual additional discount proportionally across dates
        $sumAfterCoupon = array_sum(array_column($calculatedDates, 'after_coupon'));
        $dateCount = count($calculatedDates);

        foreach ($calculatedDates as &$fDate) {
            if ($manualDiscount > 0) {
                if ($sumAfterCoupon > 0) {
                    $dateAddDiscount = round($manualDiscount * ($fDate['after_coupon'] / $sumAfterCoupon), 2);
                } else {
                    $dateAddDiscount = round($manualDiscount / $dateCount, 2);
                }
            } else {
                $dateAddDiscount = 0.00;
            }
            $fDate['additional_discount'] = min($dateAddDiscount, $fDate['after_coupon']);
        }
        unset($fDate);

        // Calculate complete financial, tax, commission, part-payment, and cancellation breakdown
        $pricing = $pricingCalculator->calculatePricing(
            $turf,
            $calculatedDates,
            $manualDiscount,
            $paymentMethod,
            $paymentOption
        );

        return response()->json(array_merge([
            'success' => true,
            'is_online_payment_active' => (bool)$turf->is_online_payment_active,
            'is_part_payment_active' => (bool)$turf->is_part_payment_active,
            'is_pay_at_location_active' => (bool)$turf->is_pay_at_location_active,
            'part_payment_type' => $turf->part_payment_type,
            'part_payment_value' => $turf->part_payment_value ? (float)$turf->part_payment_value : null,
        ], $pricing));
    }

    /**
     * Helper to check if offline payment is locked for a turf owner due to debt limits.
     */
    private function checkOfflinePaymentDebtLock(Turf $turf): ?string
    {
        $turfAdminOwner = $turf->location->user ?? null;
        if (!$turfAdminOwner) {
            return null;
        }

        $saas = \App\Models\SaasSetting::first();
        $maxDue = (float) ($saas?->max_commission_due ?? 2000.00);
        $graceDays = (int) ($saas?->commission_due_grace_days ?? 7);

        $currentBalance = (float) $turfAdminOwner->commission_wallet_balance;
        $dueDays = $turfAdminOwner->commission_due_since
            ? now()->diffInDays($turfAdminOwner->commission_due_since)
            : 0;

        if ($currentBalance <= -$maxDue || ($currentBalance < 0 && $dueDays >= $graceDays)) {
            $dueAmount = number_format(abs($currentBalance), 2);
            return "Offline booking locked! Commission due of ₹{$dueAmount} exceeds limit or grace period. Please settle your due balance from the Business page to record more offline payments.";
        }

        return null;
    }

    /**
     * Record offline cash/UPI payment for a booking date (restricted to admins/managers).
     */
    public function recordPayment(Request $request, BookingDate $bookingDate): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['saas-admin', 'turf-admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string|in:Cash,UPI,Other',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $booking = $bookingDate->booking;
        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // DEBT GUARDRAIL CHECK FOR OFFLINE PAYMENTS
        $lockError = $this->checkOfflinePaymentDebtLock($booking->turf);
        if ($lockError) {
            return response()->json(['message' => $lockError], 422);
        }

        $totalAmount = (float)($booking->total_amount > 0 ? $booking->total_amount : BookingDate::where('booking_id', $booking->id)->where('status', '!=', 'Cancelled')->sum('amount'));

        $totalPaid = (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('amount');
        $totalRemaining = max(0.00, $totalAmount - $totalPaid);
        $amountToPay = min((float)$validated['amount'], $totalRemaining);

        if ($amountToPay <= 0) {
            return response()->json(['message' => 'This booking is already fully paid.'], 422);
        }

        \DB::beginTransaction();

        try {
            $this->distributePaymentToBooking($booking, $amountToPay, $validated['payment_method']);
            \App\Services\NotificationService::notifyPaymentRecorded($booking, $amountToPay);
            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'booking_date' => $bookingDate->fresh(),
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while recording payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Distribute a payment amount proportionally across active booking dates based on their remaining balances.
     */
    private function distributePaymentToBooking(
        $booking,
        float $amountToDistribute,
        string $paymentMethod,
        ?string $razorpayPaymentId = null,
        float $gatewayCharge = 0.00,
        float $gatewayTax = 0.00,
        ?string $razorpayOrderId = null,
        ?string $razorpaySignature = null,
        ?array $responsePayload = null,
        string $paymentStatus = 'Success'
    ): void {
        (new \App\Services\BookingPaymentDistributor())->distribute(
            $booking,
            $amountToDistribute,
            $paymentMethod,
            $razorpayPaymentId,
            $gatewayCharge,
            $gatewayTax,
            $razorpayOrderId,
            $razorpaySignature,
            $responsePayload,
            $paymentStatus
        );
    }


    private function recalculateBookingPaymentStatus($booking): void
    {
        (new \App\Services\BookingPaymentDistributor())->recalculatePaymentStatus($booking);
    }


    private function getRateForTime(?array $wizard, string $day, string $time): ?float
    {
        if (!$wizard) return null;
        $sameWeek = $wizard['sameRateThroughoutWeek'] ?? 'yes';
        if ($sameWeek === 'yes') {
            $sameDay = $wizard['sameRateThroughoutDayAll'] ?? 'yes';
            if ($sameDay === 'yes') {
                return isset($wizard['flatRateAll']) && $wizard['flatRateAll'] !== '' ? (float)$wizard['flatRateAll'] : null;
            } else {
                $ranges = $wizard['timeRangesAll'] ?? [];
                foreach ($ranges as $range) {
                    $from = date('H:i', strtotime($range['from'] ?? '00:00'));
                    $to = date('H:i', strtotime($range['to'] ?? '23:59'));
                    if ($from > $to) {
                        if ($time >= $from || $time < $to) {
                            return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                        }
                    } else {
                        if ($time >= $from && $time < $to) {
                            return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                        }
                    }
                }
            }
        } else {
            $dayGroups = $wizard['dayGroups'] ?? [];
            foreach ($dayGroups as $group) {
                $days = array_map('strtolower', $group['days'] ?? []);
                if (in_array($day, $days)) {
                    $sameDay = $group['sameRateThroughoutDay'] ?? 'yes';
                    if ($sameDay === 'yes') {
                        return isset($group['flatRate']) && $group['flatRate'] !== '' ? (float)$group['flatRate'] : null;
                    } else {
                        $ranges = $group['timeRanges'] ?? [];
                        foreach ($ranges as $range) {
                            $from = date('H:i', strtotime($range['from'] ?? '00:00'));
                            $to = date('H:i', strtotime($range['to'] ?? '23:59'));
                            if ($from > $to) {
                                if ($time >= $from || $time < $to) {
                                    return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                                }
                            } else {
                                if ($time >= $from && $time < $to) {
                                    return ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : null;
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Get active coupons for a turf.
     */
    public function getCoupons(Turf $turf): JsonResponse
    {
        $coupons = \App\Models\Coupon::where('turf_id', $turf->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', Carbon::today('Asia/Kolkata')->toDateString());
            })
            ->get();

        return response()->json($coupons);
    }

    /**
     * Get statistics for Turf Admin/SaaS Admin Dashboard.
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['saas-admin', 'turf-admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $isSaasAdmin = $user->hasRole('saas-admin');
        
        $today = Carbon::today('Asia/Kolkata')->toDateString();

        // Get manageable turfs
        $turfQuery = Turf::query();
        $turfIds = [];
        if (!$isSaasAdmin) {
            $turfIds = $user->manageableTurfs()->pluck('turfs.id')->toArray();
            $turfQuery->whereIn('id', $turfIds);
        }
        $manageableTurfIds = $turfQuery->pluck('id')->toArray();

        $selectedTurfId = $request->input('turf_id');
        if ($selectedTurfId && (in_array($selectedTurfId, $manageableTurfIds) || $isSaasAdmin)) {
            $filteredTurfIds = [$selectedTurfId];
        } else {
            $filteredTurfIds = $manageableTurfIds;
        }

        // 1. Total bookings today
        $bookingsTodayQuery = BookingDate::whereDate('booking_date', $today);
        if (!$isSaasAdmin || $selectedTurfId) {
            $bookingsTodayQuery->whereHas('booking', function ($q) use ($filteredTurfIds) {
                $q->whereIn('turf_id', $filteredTurfIds);
            });
        }
        $totalBookingsToday = $bookingsTodayQuery->count();

        // 2. Total revenue today (successful payments paid_at is today)
        $paymentsTodayQuery = Payment::where('status', 'Success')
            ->whereDate('paid_at', $today);
        if (!$isSaasAdmin || $selectedTurfId) {
            $paymentsTodayQuery->whereHas('booking', function ($q) use ($filteredTurfIds) {
                $q->whereIn('turf_id', $filteredTurfIds);
            });
        }
        $totalRevenueToday = (float)$paymentsTodayQuery->sum('amount');

        // 3. Active turfs count
        $activeTurfsCount = $selectedTurfId ? 1 : count($manageableTurfIds);

        // 4. Active coupons count
        $couponsQuery = \App\Models\Coupon::where('is_active', true);
        if (!$isSaasAdmin || $selectedTurfId) {
            $couponsQuery->whereIn('turf_id', $filteredTurfIds);
        }
        $activeCouponsCount = $couponsQuery->count();

        // 5. Recent 5 bookings
        $recentBookingsQuery = BookingDate::with(['booking.turf', 'booking.user', 'payments'])
            ->orderBy('id', 'desc')
            ->limit(5);
        if (!$isSaasAdmin || $selectedTurfId) {
            $recentBookingsQuery->whereHas('booking', function ($q) use ($filteredTurfIds) {
                $q->whereIn('turf_id', $filteredTurfIds);
            });
        }
        $recentBookings = $recentBookingsQuery->get()->map(function ($bDate) {
            return [
                'id' => $bDate->id,
                'turf_name' => $bDate->booking->turf->name ?? 'N/A',
                'customer_name' => $bDate->booking->user->name ?? 'N/A',
                'date' => $bDate->booking_date,
                'amount' => (float)$bDate->amount,
                'payment_status' => $bDate->payment_status,
            ];
        });

        // Get list of selectable turfs
        $selectableTurfsQuery = Turf::query();
        if (!$isSaasAdmin) {
            $selectableTurfsQuery->whereIn('id', $manageableTurfIds);
        }
        $turfs = $selectableTurfsQuery->get(['id', 'name'])->toArray();

        return response()->json([
            'total_bookings_today' => $totalBookingsToday,
            'total_revenue_today' => $totalRevenueToday,
            'active_turfs_count' => $activeTurfsCount,
            'active_coupons_count' => $activeCouponsCount,
            'recent_bookings' => $recentBookings,
            'turfs' => $turfs,
        ]);
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Request $request, \App\Models\Booking $booking): JsonResponse
    {
        if ($booking->status === 'Cancelled') {
            return response()->json(['message' => 'Booking is already fully cancelled.'], 422);
        }

        $user = auth()->user();
        $isOwner = ($booking->user_id === $user->id);
        
        $isStaffOrAdmin = false;
        if (!$isOwner) {
            if ($user->hasRole('saas-admin')) {
                $isStaffOrAdmin = true;
            } else if ($user->hasAnyRole(['turf-admin', 'manager'])) {
                $manageableTurfIds = $user->manageableTurfs()->pluck('turfs.id')->toArray();
                if (in_array($booking->turf_id, $manageableTurfIds)) {
                    $isStaffOrAdmin = true;
                }
            }

            if (!$isStaffOrAdmin) {
                return response()->json(['message' => 'Unauthorized to cancel this booking.'], 403);
            }
        }

        $turf = $booking->turf;
        if (!$turf || !$turf->is_cancellation_active) {
            return response()->json(['message' => 'Cancellation is not allowed for this turf.'], 422);
        }

        $targetedDateIds = $request->input('booking_date_ids');
        if (empty($targetedDateIds) && $request->filled('booking_date_id')) {
            $targetedDateIds = [$request->input('booking_date_id')];
        }

        $bookingDatesQuery = $booking->bookingDates()->where('status', '!=', 'Cancelled');
        if (!empty($targetedDateIds) && is_array($targetedDateIds)) {
            $bookingDatesQuery->whereIn('id', $targetedDateIds);
        }

        $targetedDates = $bookingDatesQuery->get();
        if ($targetedDates->isEmpty()) {
            return response()->json(['message' => 'No active booking dates selected or available for cancellation.'], 422);
        }

        $cancellationHours = (int)$turf->cancellation_hours;
        $now = Carbon::now('Asia/Kolkata');

        if (!$isStaffOrAdmin) {
            foreach ($targetedDates as $bDate) {
                $earliestStart = null;
                $bDate->load(['bookingSlots.slot']);
                foreach ($bDate->bookingSlots as $bSlot) {
                    $slot = $bSlot->slot;
                    if ($slot && $slot->from_time) {
                        $dt = Carbon::parse($bDate->booking_date . ' ' . $slot->from_time, 'Asia/Kolkata');
                        if ($earliestStart === null || $dt->lt($earliestStart)) {
                            $earliestStart = $dt;
                        }
                    }
                }
                if ($earliestStart === null) {
                    $earliestStart = Carbon::parse($bDate->booking_date, 'Asia/Kolkata')->startOfDay();
                }

                $diffInHours = $now->diffInHours($earliestStart, false);
                if ($diffInHours < $cancellationHours) {
                    return response()->json([
                        'message' => "Cancellation for date {$bDate->booking_date} is only allowed up to $cancellationHours hours before session start."
                    ], 422);
                }
            }
        }

        $cancelledAt = $now;
        $cancellationFeeSetting = (float)$turf->cancellation_fee;
        $setting = \App\Models\SaasSetting::first();
        $razorpayKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $razorpaySecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');
        $platformFeePercentage = $setting ? (float)($setting->cancellation_fee_percentage ?? 5.00) : 0.00;

        $totalDatesCancelledNow = 0;
        $totalRefundProcessedNow = 0.00;
        $totalFeeAppliedNow = 0.00;

        $totalBookingPlatformFee = (float)($booking->platform_fee ?? 0) + (float)($booking->platform_fee_gst ?? 0);
        $allActiveDatesCount = $booking->bookingDates()->where('status', '!=', 'Cancelled')->count();

        foreach ($targetedDates as $bDate) {
            $successfulPayments = Payment::where('booking_date_id', $bDate->id)
                ->where('status', 'Success')
                ->get();

            $datePaidAmount = (float)$successfulPayments->sum('amount');
            if ($datePaidAmount <= 0 && $booking->payment_status === 'Paid') {
                $datePaidAmount = (float)$bDate->amount;
            }
            $dateFeeApplied = 0.00;
            $dateRefundDue = 0.00;
            $datePlatformFee = 0.00;
            $platformFee = 0.00;
            $turfFee = 0.00;
            $feeRes = null;

            if ($datePaidAmount > 0) {
                // Booking platform fee is non-refundable; exclude it from customer refund
                $datePlatformFee = ($allActiveDatesCount > 0) ? round($totalBookingPlatformFee / $allActiveDatesCount, 2) : 0.00;
                $datePlatformFee = min($datePaidAmount, $datePlatformFee);
                $slotCount = $bDate->bookingSlots()->count();

                $feeRes = (new \App\Services\CancellationFeeCalculator())->calculate($booking->turf, $datePaidAmount, $datePlatformFee, $slotCount);
                $platformFee = $feeRes['saas_fee'];
                $turfFee = $feeRes['turf_fee'];
                $dateFeeApplied = $feeRes['total_deductions'];
                $dateRefundDue = $feeRes['refund_amount'];
            }

            // Mark slots status as cancelled
            $bDate->bookingSlots()->update(['status' => 'cancelled']);

            $hasOnlineRefund = false;
            $hasOfflineRefund = false;
            $lastRazorpayRefundId = null;
            $dateCommissionReversed = 0.00;

            $remainingRefundToDistribute = $dateRefundDue;
            foreach ($successfulPayments as $payment) {
                if ($remainingRefundToDistribute <= 0) {
                    $payment->update(['refunded_amount' => 0.00, 'refund_status' => 'None']);
                    continue;
                }

                $paymentRefund = min((float)$payment->amount, $remainingRefundToDistribute);
                $isOnline = ($payment->payment_method === 'App');
                $paymentRefundStatus = $isOnline ? 'Refunded' : 'Cash / Offline Refund';

                $gateway = \App\Models\PaymentGateway::where('payment_id', $payment->id)->first();
                if ($isOnline && $gateway && $gateway->gateway_name === 'razorpay' && $gateway->gateway_payment_id) {
                    if ($razorpayKey && $razorpaySecret) {
                        try {
                            $paymentId = $gateway->gateway_payment_id;
                            $refundPaise = (int)round($paymentRefund * 100);

                            $fetchResponse = \Illuminate\Support\Facades\Http::withBasicAuth($razorpayKey, $razorpaySecret)
                                ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

                            if ($fetchResponse->successful()) {
                                $pData = $fetchResponse->json();
                                $rzpStatus = $pData['status'] ?? '';
                                $totalPaise = $pData['amount'] ?? (int)round((float)$payment->amount * 100);

                                if ($rzpStatus === 'authorized') {
                                    \Illuminate\Support\Facades\Http::withBasicAuth($razorpayKey, $razorpaySecret)
                                        ->asForm()
                                        ->post("https://api.razorpay.com/v1/payments/{$paymentId}/capture", [
                                            'amount' => $totalPaise,
                                            'currency' => $pData['currency'] ?? 'INR',
                                        ]);
                                }
                            }

                            $response = \Illuminate\Support\Facades\Http::withBasicAuth($razorpayKey, $razorpaySecret)
                                ->asForm()
                                ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", [
                                    'amount' => $refundPaise,
                                ]);

                            if ($response->successful()) {
                                $resData = $response->json();
                                $lastRazorpayRefundId = $resData['id'] ?? null;
                                $gateway->update([
                                    'gateway_refund_id' => $lastRazorpayRefundId,
                                    'refund_response_payload' => $resData,
                                ]);
                                $hasOnlineRefund = true;
                            } else {
                                $errBody = $response->json();
                                if (isset($errBody['error']['description']) && str_contains(strtolower($errBody['error']['description']), 'authorized')) {
                                    $fullPaise = (int)round((float)$payment->amount * 100);
                                    \Illuminate\Support\Facades\Http::withBasicAuth($razorpayKey, $razorpaySecret)
                                        ->asForm()
                                        ->post("https://api.razorpay.com/v1/payments/{$paymentId}/capture", [
                                            'amount' => $fullPaise,
                                            'currency' => 'INR',
                                        ]);

                                    $retryRefund = \Illuminate\Support\Facades\Http::withBasicAuth($razorpayKey, $razorpaySecret)
                                        ->asForm()
                                        ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", [
                                            'amount' => $refundPaise,
                                        ]);

                                    if ($retryRefund->successful()) {
                                        $resData = $retryRefund->json();
                                        $lastRazorpayRefundId = $resData['id'] ?? null;
                                        $gateway->update([
                                            'gateway_refund_id' => $lastRazorpayRefundId,
                                            'refund_response_payload' => $resData,
                                        ]);
                                        $hasOnlineRefund = true;
                                    } else {
                                        $gateway->update(['refund_response_payload' => $retryRefund->json()]);
                                        $paymentRefundStatus = 'Failed';
                                    }
                                } else {
                                    $gateway->update(['refund_response_payload' => $errBody]);
                                    $paymentRefundStatus = 'Failed';
                                }
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Razorpay Refund Exception: ' . $e->getMessage());
                            $paymentRefundStatus = 'Failed';
                        }
                    }
                } elseif (!$isOnline && $paymentRefund > 0) {
                    $hasOfflineRefund = true;
                }

                $payment->update([
                    'refunded_amount' => $paymentRefund,
                    'refund_status' => $paymentRefundStatus,
                    'refunded_at' => $cancelledAt,
                ]);

                // WALLET REFUND REVERSAL LOGIC: Platform retains all fees/costs; entire refund is debited from turf owner
                $turfAdminOwner = $booking->turf->location->user ?? null;
                if ($turfAdminOwner && $paymentRefund > 0) {
                    if ($payment->wallet_cleared_at) {
                        // Payment contribution was already applied to wallet -> Deduct entire refund amount
                        $reversalAmount = -round($paymentRefund, 2);
                        $walletService = new \App\Services\WalletService();
                        $walletService->applyDelta($turfAdminOwner, $reversalAmount, 'refund_adjustment', $payment, "Booking #{$booking->id} Refund Adjustment");

                        $newPayoutAmount = max(0.00, round(((float)$payment->turf_payout_amount) - $paymentRefund, 2));
                        $payment->update([
                            'turf_payout_amount' => $newPayoutAmount,
                        ]);
                    } else {
                        // Payment contribution was still pending clearance -> Reduce pending payout
                        $newPayoutAmount = max(0.00, round(((float)$payment->turf_payout_amount) - $paymentRefund, 2));
                        $payment->update([
                            'turf_payout_amount' => $newPayoutAmount,
                        ]);
                    }
                }

                $remainingRefundToDistribute -= $paymentRefund;
            }

            if ($datePaidAmount > 0 && $dateRefundDue > 0) {
                if ($hasOnlineRefund) {
                    $dateRefundStatus = 'Refunded';
                } elseif ($hasOfflineRefund) {
                    $dateRefundStatus = 'Cash / Offline Refund';
                } else {
                    $dateRefundStatus = 'Refunded';
                }
                $dateRefundedAt = $cancelledAt;
            } else {
                $dateRefundStatus = 'Not Applicable';
                $dateRefundedAt = null;
            }

            $bDate->update([
                'status' => 'Cancelled',
                'cancelled_at' => $cancelledAt,
                'cancellation_fee_applied' => $dateFeeApplied,
                'refund_amount' => $dateRefundDue,
                'refund_status' => $dateRefundStatus,
                'refunded_at' => $dateRefundedAt,
            ]);

            // Create Master Cancellation Event Audit Record
            \App\Models\BookingCancellation::create([
                'booking_id' => $booking->id,
                'booking_date_id' => $bDate->id,
                'cancelled_by_user_id' => $user->id,
                'canceller_role' => $user->roles()->pluck('name')->first() ?? 'customer',
                'cancellation_scope' => (count($targetedDates) === $booking->bookingDates()->count()) ? 'full_booking' : 'date',
                'reason' => $request->input('reason', 'Customer requested cancellation'),
                'gross_cancelled_amount' => $datePaidAmount,
                'turf_cancellation_fee' => $turfFee ?? 0.00,
                'turf_fee_gst_amount' => $feeRes['turf_fee_gst'] ?? 0.00,
                'turf_fee_cgst_amount' => $feeRes['turf_fee_cgst'] ?? 0.00,
                'turf_fee_sgst_amount' => $feeRes['turf_fee_sgst'] ?? 0.00,
                'platform_fee_retained' => $datePlatformFee ?? 0.00,
                'saas_cancellation_fee' => $platformFee ?? 0.00,
                'saas_fee_gst_amount' => $feeRes['saas_fee_gst'] ?? 0.00,
                'saas_fee_cgst_amount' => $feeRes['saas_fee_cgst'] ?? 0.00,
                'saas_fee_sgst_amount' => $feeRes['saas_fee_sgst'] ?? 0.00,
                'saas_fee_igst_amount' => $feeRes['saas_fee_igst'] ?? 0.00,
                'platform_cancellation_fee' => round(($platformFee ?? 0.00) + ($datePlatformFee ?? 0.00), 2),
                'total_cancellation_fee' => $dateFeeApplied,
                'refund_amount' => $dateRefundDue,
                'refund_status' => $dateRefundStatus,
                'razorpay_refund_id' => $lastRazorpayRefundId,
                'commission_reversed_amount' => $dateCommissionReversed,
            ]);

            $totalDatesCancelledNow++;
            $totalRefundProcessedNow += $dateRefundDue;
            $totalFeeAppliedNow += $dateFeeApplied;
        }

        $allDates = $booking->bookingDates()->get();
        $totalDatesCount = $allDates->count();
        $cancelledDatesCount = $allDates->where('status', 'Cancelled')->count();

        $aggregateFee = (float)$allDates->sum('cancellation_fee_applied');
        $aggregateRefund = (float)$allDates->sum('refund_amount');
        $earliestCancelledAt = $allDates->whereNotNull('cancelled_at')->min('cancelled_at');
        $latestRefundedAt = $allDates->whereNotNull('refunded_at')->max('refunded_at');

        $parentStatus = 'Confirmed';
        if ($cancelledDatesCount === $totalDatesCount) {
            $parentStatus = 'Cancelled';
        } elseif ($cancelledDatesCount > 0) {
            $parentStatus = 'Partially Cancelled';
        }

        $parentRefundStatus = 'Not Applicable';
        if ($aggregateRefund > 0) {
            $hasAnyOnlineRefund = $allDates->where('refund_status', 'Refunded')->isNotEmpty();
            $parentRefundStatus = $hasAnyOnlineRefund ? 'Refunded' : 'Cash / Offline Refund';
        }

        $booking->update([
            'status' => $parentStatus,
            'cancelled_at' => $earliestCancelledAt,
            'cancellation_fee_applied' => $aggregateFee,
            'refund_amount' => $aggregateRefund,
            'refund_status' => $parentRefundStatus,
            'refunded_at' => $latestRefundedAt,
        ]);

        $feeMsg = $totalFeeAppliedNow > 0 ? " Cancellation fee of ₹" . number_format($totalFeeAppliedNow, 2) . " applied." : "";
        $refundMsg = $totalRefundProcessedNow > 0 ? " Refund of ₹" . number_format($totalRefundProcessedNow, 2) . " processed to original payment method." : " No refund applicable.";

        \App\Services\NotificationService::notifyBookingCancelled($booking);

        return response()->json([
            'message' => "Successfully cancelled $totalDatesCancelledNow booking date(s)." . $feeMsg . $refundMsg,
            'booking' => $booking->fresh(['bookingDates', 'payments.paymentGateway'])
        ]);
    }

    /**
     * Create a Razorpay order for mobile checkout.
     */
    public function createRazorpayOrder(Request $request, Turf $turf): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
        ]);

        $amount = (float) $validated['amount'];
        $amountInPaise = (int) round($amount * 100);

        $setting = \App\Models\SaasSetting::first();
        $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        if (!$rzpKey || !$rzpSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway keys are not configured.',
                'order_id' => null,
                'key' => $rzpKey,
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($rzpKey, $rzpSecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountInPaise,
                    'currency' => $validated['currency'] ?? 'INR',
                    'receipt' => 'bkg_' . time() . '_' . auth()->id(),
                    'notes' => [
                        'turf_id' => $turf->id,
                        'turf_name' => $turf->name,
                        'user_id' => auth()->id(),
                    ],
                ]);

            if ($response->successful()) {
                $orderData = $response->json();
                return response()->json([
                    'success' => true,
                    'order_id' => $orderData['id'],
                    'amount' => $orderData['amount'],
                    'currency' => $orderData['currency'],
                    'key' => $rzpKey,
                ]);
            }

            \Illuminate\Support\Facades\Log::error('Razorpay order creation failed: ' . $response->body());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment order from gateway.',
                'error' => $response->json(),
                'key' => $rzpKey,
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Razorpay order exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Exception during order creation.',
                'error' => $e->getMessage(),
                'key' => $rzpKey,
            ], 500);
        }
    }
}

