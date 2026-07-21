<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
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
        
        $query = BookingDate::with(['booking.turf', 'booking.user', 'bookingSlots.slot', 'payments']);

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
            $query->whereDate('booking_date', $date)
                  ->orderBy('id', 'asc');
        } else {
            if ($filter === 'past') {
                $query->where('booking_date', '<', $today)
                      ->orderBy('booking_date', 'desc')
                      ->orderBy('id', 'desc');
            } else {
                // Default to upcoming (today or future)
                $query->where('booking_date', '>=', $today)
                      ->orderBy('booking_date', 'asc')
                      ->orderBy('id', 'asc');
            }
        }

        $bookingDates = $query->paginate(10);
            
        $formatted = $bookingDates->through(function ($bDate) {
            $booking = $bDate->booking;
            
            $slots = [];
            foreach ($bDate->bookingSlots as $bSlot) {
                $slot = $bSlot->slot;
                if ($slot) {
                    $slots[] = [
                        'id' => $slot->id,
                        'time_range' => ($slot->from_time && $slot->to_time)
                            ? date('h:i A', strtotime($slot->from_time)) . ' - ' . date('h:i A', strtotime($slot->to_time))
                            : 'N/A',
                        'duration' => $slot->duration,
                    ];
                }
            }

            // Summary of slots for this date
            $slotCount = count($slots);
            $summaryText = $slotCount . ' ' . ($slotCount === 1 ? 'slot' : 'slots') . ' booked';

            // Calculate overall booking payment status metrics
            $totalBookingAmount = 0.00;
            $totalPaidAmount = 0.00;
            if ($booking) {
                $totalBookingAmount = (float) BookingDate::where('booking_id', $booking->id)->sum('amount');
                $totalPaidAmount = (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('amount');
            }
            $balanceAmount = max(0.00, $totalBookingAmount - $totalPaidAmount);

            // Date-specific payment metrics
            $datePaidAmount = (float) $bDate->payments()->where('status', 'Success')->sum('amount');
            $dateBalanceAmount = max(0.00, (float)$bDate->amount - $datePaidAmount);

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
                'status' => $booking->status ?? 'Pending',
                'payment_status' => $booking->payment_status ?? 'Pending',
                'date_payment_status' => $bDate->payment_status ?? 'Unpaid',
                'amount' => (float)$bDate->amount,
                'price' => '₹' . number_format($bDate->amount, 0),
                'summary_text' => $summaryText,
                'slots' => $slots,
                
                'total_booking_amount' => $totalBookingAmount,
                'total_paid_amount' => $totalPaidAmount,
                'balance_amount' => $balanceAmount,
                'date_paid_amount' => $datePaidAmount,
                'date_balance_amount' => $dateBalanceAmount,
                
                'customer_name' => $booking->user->name ?? 'N/A',
                'customer_email' => $booking->user->email ?? 'N/A',
                'customer_mobile' => $booking->user->mobile ?? 'N/A',
                'share_message_template' => $booking->turf->share_message_template ?? null,
                'is_cancellation_active' => $booking->turf ? (bool)$booking->turf->is_cancellation_active : false,
                'cancellation_hours' => $booking->turf ? (int)$booking->turf->cancellation_hours : 0,
                'cancellation_fee' => $booking->turf ? (float)$booking->turf->cancellation_fee : 0.00,
                'cancelled_at' => $booking->cancelled_at ? Carbon::parse($booking->cancelled_at)->format('F d, Y h:i A') : null,
                'cancellation_fee_applied' => (float)$booking->cancellation_fee_applied,
                'refund_amount' => (float)$booking->refund_amount,
                'refund_status' => $booking->refund_status ?? 'None',
                'refunded_at' => $booking->refunded_at ? Carbon::parse($booking->refunded_at)->format('F d, Y h:i A') : null,
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

        return response()->json($formatted);
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

        $occupiedSlotIds = \App\Models\BookingSlot::whereHas('bookingDate', function ($q) use ($turf, $dates) {
            $q->whereIn('booking_date', $dates)
              ->whereHas('booking', function ($bq) use ($turf) {
                  $bq->where('turf_id', $turf->id)
                     ->where('status', 'Confirmed');
              });
        })
        ->pluck('slot_id')
        ->toArray();

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
            ->map(function ($slot) use ($dayOfWeek, $occupiedSlotIds, $wizard, $isTodaySelected, $nowTime) {
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

                $isPast = ($isTodaySelected && $slot->from_time < $nowTime);

                return [
                    'id' => $slot->id,
                    'from_time' => $slot->from_time,
                    'to_time' => $slot->to_time,
                    'time_label' => "$fromFormatted - $toFormatted",
                    'price' => $price,
                    'is_booked' => in_array($slot->id, $occupiedSlotIds) || $isPast,
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
            'payment_method' => 'required|string|in:offline,App,Cash,UPI,Other',
            'payment_option' => 'nullable|string|in:full,part',
            'amount_received' => 'nullable|numeric|min:0', // for manager
            'customer_id' => 'nullable|exists:users,id', // for manager
            'razorpay_payment_id' => 'nullable|string',
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

        $settings = \App\Models\SaasSetting::first();
        $minSlots = $settings?->min_slots_booking ?? 2;

        if (count($slotIds) < $minSlots) {
            return response()->json([
                'message' => "You must book a minimum of {$minSlots} slots.",
            ], 422);
        }

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

        // We will perform a transaction to ensure atomic bookings
        \DB::beginTransaction();

        try {
            $totalSubtotal = 0.00;
            $totalDiscount = 0.00;
            $calculatedDates = [];

            foreach ($dates as $dateStr) {
                $dateObj = Carbon::parse($dateStr);
                $dayOfWeek = strtolower($dateObj->format('D'));
                $dateSubtotal = 0.00;
                $slotsToCreate = [];

                foreach ($slotIds as $slotId) {
                    $alreadyBooked = \App\Models\BookingSlot::where('slot_id', $slotId)
                        ->whereHas('bookingDate', function ($q) use ($turf, $dateStr) {
                            $q->where('booking_date', $dateStr)
                              ->whereHas('booking', function ($bq) use ($turf) {
                                  $bq->where('turf_id', $turf->id)
                                     ->where('status', 'Confirmed');
                              });
                        })
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

                $dateNet = max(0.00, $dateSubtotal - $couponDiscount);
                $totalSubtotal += $dateSubtotal;
                $totalDiscount += $couponDiscount;

                $calculatedDates[] = [
                    'date_str' => $dateStr,
                    'subtotal' => $dateSubtotal,
                    'discount' => $couponDiscount,
                    'net_amount' => $dateNet,
                    'slots' => $slotsToCreate,
                    'coupon' => $coupon,
                ];
            }

            $totalAmount = max(0.00, $totalSubtotal - $totalDiscount);

            // Create parent booking record
            $booking = Booking::create([
                'user_id' => $targetUserId,
                'turf_id' => $turf->id,
                'date_of_booking' => Carbon::now(),
                'booking_type' => $bookingType,
                'status' => 'Confirmed',
                'payment_status' => 'Pending',
                'additional_discount' => $totalDiscount,
            ]);

            // Save booking dates
            $bookingDatesCreated = [];
            foreach ($calculatedDates as $calcDate) {
                $bookingDate = $booking->bookingDates()->create([
                    'booking_date' => $calcDate['date_str'],
                    'amount' => $calcDate['net_amount'],
                    'additional_discount' => $calcDate['discount'],
                    'payment_status' => 'Unpaid',
                ]);

                foreach ($calcDate['slots'] as $slotId) {
                    $bookingDate->bookingSlots()->create([
                        'slot_id' => $slotId,
                    ]);
                }

                if ($calcDate['coupon'] && $calcDate['discount'] > 0) {
                    \App\Models\CouponUsage::create([
                        'coupon_id' => $calcDate['coupon']->id,
                        'user_id' => $targetUserId,
                        'booking_date_id' => $bookingDate->id,
                        'discount_applied' => $calcDate['discount'],
                        'used_at' => Carbon::now(),
                    ]);
                    $calcDate['coupon']->increment('used_count');
                }

                $bookingDatesCreated[] = $bookingDate;
            }

            // Distribute paid amount and create payment records
            if ($isStaffOrAdmin && $customerId) {
                // Manager/Admin booking for customer:
                $amountReceived = (float)($validated['amount_received'] ?? 0.00);

                if ($amountReceived > 0) {
                    $remainingToDistribute = $amountReceived;
                    foreach ($bookingDatesCreated as $bDate) {
                        if ($remainingToDistribute <= 0) break;

                        $dateOwed = $bDate->amount;
                        $paidForDate = min($remainingToDistribute, $dateOwed);

                        if ($paidForDate > 0) {
                            Payment::create([
                                'booking_id' => $booking->id,
                                'booking_date_id' => $bDate->id,
                                'payment_method' => $paymentMethod, // Cash, UPI, Other
                                'amount' => $paidForDate,
                                'status' => 'Success',
                                'paid_at' => Carbon::now(),
                            ]);
                            $remainingToDistribute -= $paidForDate;
                        }
                    }
                }
            } else {
                // Customer booking:
                if ($paymentMethod === 'App') {
                    $partPaymentActive = $turf->is_part_payment_active ?? false;
                    $paymentOption = $validated['payment_option'] ?? 'full';
                    
                    $paidAmount = $totalAmount;
                    if ($partPaymentActive && $paymentOption === 'part') {
                        $partType = $turf->part_payment_type ?? 'percentage';
                        $partVal = (float)($turf->part_payment_value ?? 0.00);
                        if ($partType === 'percentage') {
                            $paidAmount = round($totalAmount * ($partVal / 100), 2);
                        } else {
                            $paidAmount = min($partVal, $totalAmount);
                        }
                    }

                    if ($paidAmount > 0) {
                        if ($request->filled('razorpay_payment_id')) {
                            $rzpId = $request->input('razorpay_payment_id');
                            $setting = \App\Models\SaasSetting::first();
                            $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
                            $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

                            if ($rzpKey && $rzpSecret) {
                                try {
                                    $fetch = \Illuminate\Support\Facades\Http::withBasicAuth($rzpKey, $rzpSecret)
                                        ->get("https://api.razorpay.com/v1/payments/{$rzpId}");
                                    if ($fetch->successful()) {
                                        $pData = $fetch->json();
                                        if (($pData['status'] ?? '') === 'authorized') {
                                            \Illuminate\Support\Facades\Http::withBasicAuth($rzpKey, $rzpSecret)
                                                ->asForm()
                                                ->post("https://api.razorpay.com/v1/payments/{$rzpId}/capture", [
                                                    'amount' => $pData['amount'],
                                                    'currency' => $pData['currency'] ?? 'INR',
                                                ]);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Razorpay auto-capture error on store: ' . $e->getMessage());
                                }
                            }
                        }

                        $remainingToDistribute = $paidAmount;
                        foreach ($bookingDatesCreated as $index => $bDate) {
                            if ($remainingToDistribute <= 0) break;

                            $dateOwed = $bDate->amount;
                            if ($index === count($bookingDatesCreated) - 1) {
                                $paidForDate = $remainingToDistribute;
                            } else {
                                $paidForDate = round($paidAmount * ($dateOwed / $totalAmount), 2);
                                $paidForDate = min($paidForDate, $remainingToDistribute);
                            }

                            if ($paidForDate > 0) {
                                $payment = Payment::create([
                                    'booking_id' => $booking->id,
                                    'booking_date_id' => $bDate->id,
                                    'payment_method' => 'App',
                                    'amount' => $paidForDate,
                                    'status' => 'Success',
                                    'paid_at' => Carbon::now(),
                                ]);

                                PaymentGateway::create([
                                    'payment_id' => $payment->id,
                                    'gateway_name' => 'razorpay',
                                    'gateway_payment_id' => $request->input('razorpay_payment_id'),
                                ]);

                                $remainingToDistribute -= $paidForDate;
                            }
                        }
                    }
                }
            }

            // Recalculate payment status of each date and overall booking
            $booking->load('bookingDates');
            $allDatesPaid = true;
            $anyDatePaid = false;

            foreach ($booking->bookingDates as $bDate) {
                $datePaidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');
                if ($datePaidSum >= $bDate->amount && $bDate->amount > 0) {
                    $bDate->update(['payment_status' => 'Paid']);
                    $anyDatePaid = true;
                } elseif ($datePaidSum > 0) {
                    $bDate->update(['payment_status' => 'Partially Paid']);
                    $allDatesPaid = false;
                    $anyDatePaid = true;
                } else {
                    $bDate->update(['payment_status' => 'Unpaid']);
                    $allDatesPaid = false;
                }
            }

            if ($allDatesPaid && $totalAmount > 0) {
                $booking->update(['payment_status' => 'Paid']);
            } elseif ($anyDatePaid) {
                $booking->update(['payment_status' => 'Partially Paid']);
            } else {
                $booking->update(['payment_status' => 'Unpaid']);
            }

            \DB::commit();

            return response()->json([
                'message' => 'Turf booked successfully!',
                'booking' => $booking->load('bookingDates.bookingSlots.slot'),
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while booking. Please try again.',
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
        ]);

        $slotIds = $validated['slot_ids'];
        $dates = $validated['booking_dates'];
        $bookingType = $validated['booking_type'];
        $dateCoupons = $validated['coupons'] ?? [];

        $wizard = is_array($turf->pricing_wizard_data) 
            ? $turf->pricing_wizard_data 
            : json_decode($turf->pricing_wizard_data, true);

        $totalSubtotal = 0.00;
        $totalDiscount = 0.00;
        $formattedDates = [];

        foreach ($dates as $dateStr) {
            $dateObj = Carbon::parse($dateStr);
            $dayOfWeek = strtolower($dateObj->format('D'));
            $dateSubtotal = 0.00;
            $slotsData = [];
            
            foreach ($slotIds as $slotId) {
                $isBooked = \App\Models\BookingSlot::where('slot_id', $slotId)
                    ->whereHas('bookingDate', function ($q) use ($turf, $dateStr) {
                        $q->where('booking_date', $dateStr)
                          ->whereHas('booking', function ($bq) use ($turf) {
                              $bq->where('turf_id', $turf->id)
                                 ->where('status', 'Confirmed');
                          });
                    })
                    ->exists();

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

                if (!$isBooked) {
                    $dateSubtotal += $price;
                }

                $slotsData[] = [
                    'id' => $slotId,
                    'time_label' => date('h:i A', strtotime($slot->from_time)) . ' - ' . date('h:i A', strtotime($slot->to_time)),
                    'price' => $price,
                    'status' => $isBooked ? 'booked' : 'available',
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

            $dateNet = max(0.00, $dateSubtotal - $couponDiscount);
            $totalSubtotal += $dateSubtotal;
            $totalDiscount += $couponDiscount;

            $formattedDates[] = [
                'date' => $dateStr,
                'day_name' => ucfirst($dayOfWeek),
                'subtotal' => $dateSubtotal,
                'discount' => $couponDiscount,
                'net_amount' => $dateNet,
                'slots' => $slotsData,
                'coupon' => [
                    'applied' => $couponApplied,
                    'code' => $couponCode,
                    'discount' => $couponDiscount,
                    'error' => $couponError,
                ]
            ];
        }

        $totalAmount = max(0.00, $totalSubtotal - $totalDiscount);

        // Part payment calculations
        $partPaymentActive = $turf->is_part_payment_active ?? false;
        $payableNow = $totalAmount;
        $remainingBalance = 0.00;

        if ($partPaymentActive) {
            $partType = $turf->part_payment_type ?? 'percentage';
            $partVal = (float)($turf->part_payment_value ?? 0.00);

            if ($partType === 'percentage') {
                $payableNow = round($totalAmount * ($partVal / 100), 2);
            } else {
                $payableNow = min($partVal, $totalAmount);
            }
            $remainingBalance = round($totalAmount - $payableNow, 2);
        }

        return response()->json([
            'success' => true,
            'subtotal' => $totalSubtotal,
            'discount' => $totalDiscount,
            'total_amount' => $totalAmount,
            'part_payment_active' => $partPaymentActive,
            'payable_now' => $payableNow,
            'remaining_balance' => $remainingBalance,
            'dates' => $formattedDates,
        ]);
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

        $currentPaid = (float) Payment::where('booking_date_id', $bookingDate->id)->where('status', 'Success')->sum('amount');
        $remaining = max(0.00, $bookingDate->amount - $currentPaid);
        $amountToPay = min((float)$validated['amount'], $remaining);

        if ($amountToPay <= 0) {
            return response()->json(['message' => 'This date is already fully paid.'], 422);
        }

        \DB::beginTransaction();

        try {
            Payment::create([
                'booking_id' => $booking->id,
                'booking_date_id' => $bookingDate->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amountToPay,
                'status' => 'Success',
                'paid_at' => Carbon::now(),
            ]);

            $newPaidSum = $currentPaid + $amountToPay;
            if ($newPaidSum >= $bookingDate->amount) {
                $bookingDate->update(['payment_status' => 'Paid']);
            } else {
                $bookingDate->update(['payment_status' => 'Partially Paid']);
            }

            $booking->load('bookingDates');
            $allPaid = true;
            $anyPaid = false;
            foreach ($booking->bookingDates as $bd) {
                $bdPaidSum = (float) Payment::where('booking_date_id', $bd->id)->where('status', 'Success')->sum('amount');
                if ($bdPaidSum >= $bd->amount) {
                    $bd->update(['payment_status' => 'Paid']);
                    $anyPaid = true;
                } elseif ($bdPaidSum > 0) {
                    $bd->update(['payment_status' => 'Partially Paid']);
                    $allPaid = false;
                    $anyPaid = true;
                } else {
                    $bd->update(['payment_status' => 'Unpaid']);
                    $allPaid = false;
                }
            }

            $totalAmount = (float) BookingDate::where('booking_id', $booking->id)->sum('amount');
            if ($allPaid && $totalAmount > 0) {
                $booking->update(['payment_status' => 'Paid']);
            } elseif ($anyPaid) {
                $booking->update(['payment_status' => 'Partially Paid']);
            } else {
                $booking->update(['payment_status' => 'Unpaid']);
            }

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
            return response()->json(['message' => 'Booking is already cancelled.'], 422);
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

        if (!$isStaffOrAdmin) {
            $cancellationHours = (int)$turf->cancellation_hours;
            
            $earliestStart = null;
            $booking->load(['bookingDates.bookingSlots.slot']);
            foreach ($booking->bookingDates as $bDate) {
                $dateStr = $bDate->booking_date;
                foreach ($bDate->bookingSlots as $bSlot) {
                    $slot = $bSlot->slot;
                    if ($slot && $slot->from_time) {
                        $dt = Carbon::parse($dateStr . ' ' . $slot->from_time, 'Asia/Kolkata');
                        if ($earliestStart === null || $dt->lt($earliestStart)) {
                            $earliestStart = $dt;
                        }
                    }
                }
                if ($earliestStart === null) {
                    $dt = Carbon::parse($dateStr, 'Asia/Kolkata')->startOfDay();
                    if ($earliestStart === null || $dt->lt($earliestStart)) {
                        $earliestStart = $dt;
                    }
                }
            }

            if ($earliestStart) {
                $now = Carbon::now('Asia/Kolkata');
                $diffInHours = $now->diffInHours($earliestStart, false);
                if ($diffInHours < $cancellationHours) {
                    return response()->json([
                        'message' => "Cancellation is only allowed up to $cancellationHours hours before the booking starts."
                    ], 422);
                }
            }
        }

        $cancelledAt = Carbon::now('Asia/Kolkata');
        $cancellationFeeSetting = (float)$turf->cancellation_fee;

        $successfulPayments = Payment::where('booking_id', $booking->id)
            ->where('status', 'Success')
            ->get();
            
        $totalPaidAmount = (float)$successfulPayments->sum('amount');
        
        $cancellationFeeApplied = 0.00;
        $totalRefundDue = 0.00;

        if ($totalPaidAmount > 0) {
            $cancellationFeeApplied = min($totalPaidAmount, $cancellationFeeSetting);
            $totalRefundDue = max(0.00, $totalPaidAmount - $cancellationFeeApplied);
        }

        $refundStatus = 'Not Applicable';
        $refundedAt = null;

        if ($totalPaidAmount > 0) {
            if ($totalRefundDue > 0) {
                $refundStatus = 'Refunded';
                $refundedAt = $cancelledAt;
            } else {
                $refundStatus = 'Not Applicable';
            }
        }

        $booking->update([
            'status' => 'Cancelled',
            'cancelled_at' => $cancelledAt,
            'cancellation_fee_applied' => $cancellationFeeApplied,
            'refund_amount' => $totalRefundDue,
            'refund_status' => $refundStatus,
            'refunded_at' => $refundedAt,
        ]);

        $remainingRefundToDistribute = $totalRefundDue;
        $setting = \App\Models\SaasSetting::first();
        $razorpayKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $razorpaySecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        foreach ($successfulPayments as $payment) {
            if ($remainingRefundToDistribute <= 0) {
                $payment->update([
                    'refunded_amount' => 0.00,
                    'refund_status' => 'None',
                ]);
                continue;
            }

            $paymentRefund = min((float)$payment->amount, $remainingRefundToDistribute);
            $paymentRefundStatus = 'Refunded';

            $gateway = \App\Models\PaymentGateway::where('payment_id', $payment->id)->first();
            if ($gateway && $gateway->gateway_name === 'razorpay' && $gateway->gateway_payment_id) {
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
                            $gateway->update([
                                'gateway_refund_id' => $resData['id'] ?? null,
                                'refund_response_payload' => $resData,
                            ]);
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
                                    $gateway->update([
                                        'gateway_refund_id' => $resData['id'] ?? null,
                                        'refund_response_payload' => $resData,
                                    ]);
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
                    }
                }
            }

            $payment->update([
                'refunded_amount' => $paymentRefund,
                'refund_status' => $paymentRefundStatus,
                'refunded_at' => $cancelledAt,
            ]);

            $remainingRefundToDistribute -= $paymentRefund;
        }

        $feeMsg = $cancellationFeeApplied > 0 ? " Cancellation fee of ₹" . number_format($cancellationFeeApplied, 0) . " applied." : "";
        $refundMsg = $totalRefundDue > 0 ? " Refund of ₹" . number_format($totalRefundDue, 0) . " processed to original payment method." : " No refund applicable.";

        return response()->json([
            'message' => 'Booking cancelled successfully.' . $feeMsg . $refundMsg,
            'booking' => $booking->fresh(['bookingDates', 'payments.paymentGateway'])
        ]);
    }
}
