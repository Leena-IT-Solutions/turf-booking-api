<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Get bookings made by the authenticated user.
     */
    public function index(): JsonResponse
    {
        $userId = auth()->id();
        
        $bookings = Booking::with(['turf', 'bookingDates.bookingSlots.slot'])
            ->where('user_id', $userId)
            ->orderBy('date_of_booking', 'desc')
            ->get();
            
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            foreach ($booking->bookingDates as $bDate) {
                foreach ($bDate->bookingSlots as $bSlot) {
                    $slot = $bSlot->slot;
                    $formattedBookings[] = [
                        'id' => $booking->id,
                        'turf_name' => $booking->turf->name ?? 'Unknown Turf',
                        'date' => Carbon::parse($bDate->booking_date)->format('F d, Y'),
                        'time' => ($slot && $slot->from_time && $slot->to_time) 
                            ? date('h:i A', strtotime($slot->from_time)) . ' - ' . date('h:i A', strtotime($slot->to_time))
                            : 'N/A',
                        'status' => $booking->status,
                        'price' => '₹' . number_format($bDate->amount / max(1, count($bDate->bookingSlots)), 0),
                    ];
                }
            }
        }

        return response()->json($formattedBookings);
    }

    /**
     * Get available and occupied slots for a turf on a specific date.
     */
    public function getSlots(Request $request, Turf $turf): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $dateStr = $validated['date'];
        $date = Carbon::parse($dateStr);
        $dayOfWeek = strtolower($date->format('D')); // 'mon', 'tue', etc.

        // Get occupied slots on this date
        $occupiedSlotIds = \App\Models\BookingSlot::whereHas('bookingDate', function ($q) use ($turf, $dateStr) {
            $q->where('booking_date', $dateStr)
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

        // Format all turf slots
        $slots = $turf->slots()
            ->with('category')
            ->wherePivot('is_active', true)
            ->get()
            ->map(function ($slot) use ($dayOfWeek, $occupiedSlotIds, $wizard) {
                // Determine slot price
                $fromTime24 = date('H:i', strtotime($slot->from_time));
                $hourlyRate = $this->getRateForTime($wizard, $dayOfWeek, $fromTime24);
                $duration = intval($slot->duration ?: 30);

                if ($hourlyRate !== null) {
                    $price = ($hourlyRate / 60) * $duration;
                } else {
                    if (isset($slot->pivot->$dayOfWeek)) {
                        $price = (float)$slot->pivot->$dayOfWeek;
                    } else {
                        $price = (1000.00 / 60) * $duration;
                    }
                }

                // Format time to 12 hour AM/PM
                $fromFormatted = date('h:i A', strtotime($slot->from_time));
                $toFormatted = date('h:i A', strtotime($slot->to_time));

                return [
                    'id' => $slot->id,
                    'from_time' => $slot->from_time,
                    'to_time' => $slot->to_time,
                    'time_label' => "$fromFormatted - $toFormatted",
                    'price' => $price,
                    'is_booked' => in_array($slot->id, $occupiedSlotIds),
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
            'coupon_code' => 'nullable|string',
            'payment_method' => 'required|string|in:offline,razorpay',
            'razorpay_payment_id' => 'nullable|string|required_if:payment_method,razorpay',
        ]);

        $userId = auth()->id();
        $slotIds = $validated['slot_ids'];
        $dates = $validated['booking_dates'];
        $bookingType = $validated['booking_type'];
        $couponCode = $validated['coupon_code'] ?? null;
        $paymentMethod = $validated['payment_method'];
        $razorpayPaymentId = $validated['razorpay_payment_id'] ?? null;

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

        // Verify Coupon if provided
        $coupon = null;
        if ($couponCode) {
            $coupon = \App\Models\Coupon::where('turf_id', $turf->id)
                ->where('code', $couponCode)
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

            $slotCount = count($slotIds);
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
        }

        // Get pricing wizard details helper
        $wizard = is_array($turf->pricing_wizard_data) 
            ? $turf->pricing_wizard_data 
            : json_decode($turf->pricing_wizard_data, true);

        // We will perform a transaction to ensure atomic bookings
        \DB::beginTransaction();

        try {
            $totalBookingAmountBeforeDiscount = 0.00;
            $calculatedDates = [];

            foreach ($dates as $dateStr) {
                $date = Carbon::parse($dateStr);
                $dayOfWeek = strtolower($date->format('D'));
                $dateAmount = 0.00;
                $slotsToCreate = [];

                foreach ($slotIds as $slotId) {
                    // Check if slot is already booked
                    $alreadyBooked = \App\Models\BookingSlot::where('slot_id', $slotId)
                        ->whereHas('bookingDate', function ($q) use ($dateStr) {
                            $q->where('booking_date', $dateStr)
                              ->whereHas('booking', function ($bq) {
                                  $bq->where('status', 'Confirmed');
                              });
                        })
                        ->exists();

                    if ($alreadyBooked) {
                        \DB::rollBack();
                        return response()->json([
                            'message' => "Slot is already booked for date: $dateStr.",
                        ], 422);
                    }

                    // Get the slot model to calculate price
                    $slot = $turf->slots()->where('slots.id', $slotId)->first();
                    if (!$slot) {
                        \DB::rollBack();
                        return response()->json([
                            'message' => "Invalid slot ID for this turf.",
                        ], 422);
                    }

                    // Price calculation logic
                    $fromTime24 = date('H:i', strtotime($slot->from_time));
                    $hourlyRate = $this->getRateForTime($wizard, $dayOfWeek, $fromTime24);
                    $duration = intval($slot->duration ?: 30);

                    if ($hourlyRate !== null) {
                        $price = ($hourlyRate / 60) * $duration;
                    } else {
                        if (isset($slot->pivot->$dayOfWeek)) {
                            $price = (float)$slot->pivot->$dayOfWeek;
                        } else {
                            $price = (1000.00 / 60) * $duration;
                        }
                    }

                    $dateAmount += $price;
                    $slotsToCreate[] = $slotId;
                }

                $totalBookingAmountBeforeDiscount += $dateAmount;
                $calculatedDates[] = [
                    'date_str' => $dateStr,
                    'subtotal' => $dateAmount,
                    'slots' => $slotsToCreate,
                ];
            }

            // Calculate overall discount
            $totalDiscountApplied = 0.00;
            if ($coupon) {
                if ($coupon->discount_type === 'fixed') {
                    $totalDiscountApplied = (float)$coupon->discount_value;
                } else {
                    $totalDiscountApplied = $totalBookingAmountBeforeDiscount * ($coupon->discount_value / 100);
                }
                if ($coupon->max_discount_amount !== null && $totalDiscountApplied > $coupon->max_discount_amount) {
                    $totalDiscountApplied = (float)$coupon->max_discount_amount;
                }
            }

            if ($totalDiscountApplied > $totalBookingAmountBeforeDiscount) {
                $totalDiscountApplied = $totalBookingAmountBeforeDiscount;
            }

            // Create parent booking record
            $booking = Booking::create([
                'user_id' => $userId,
                'turf_id' => $turf->id,
                'date_of_booking' => Carbon::now(),
                'booking_type' => $bookingType,
                'status' => 'Confirmed',
                'payment_status' => $paymentMethod === 'razorpay' ? 'Paid' : 'Pending',
                'additional_discount' => $totalDiscountApplied,
            ]);

            // Distribute discount proportionally across booking dates
            $remainingDiscount = $totalDiscountApplied;
            $numDates = count($calculatedDates);

            foreach ($calculatedDates as $index => $calcDate) {
                if ($index === $numDates - 1) {
                    $dateDiscount = $remainingDiscount;
                } else {
                    $dateDiscount = round($totalDiscountApplied * ($calcDate['subtotal'] / $totalBookingAmountBeforeDiscount), 2);
                    $remainingDiscount -= $dateDiscount;
                }

                $netAmount = max(0, $calcDate['subtotal'] - $dateDiscount);

                // Create BookingDate record
                $bookingDate = $booking->bookingDates()->create([
                    'booking_date' => $calcDate['date_str'],
                    'amount' => $netAmount,
                    'additional_discount' => $dateDiscount,
                ]);

                // Create BookingSlot records
                foreach ($calcDate['slots'] as $slotId) {
                    $bookingDate->bookingSlots()->create([
                        'slot_id' => $slotId,
                    ]);
                }

                // Record Coupon Usage
                if ($coupon && $dateDiscount > 0) {
                    \App\Models\CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id' => $userId,
                        'booking_date_id' => $bookingDate->id,
                        'discount_applied' => $dateDiscount,
                        'used_at' => Carbon::now(),
                    ]);
                }
            }

            if ($coupon) {
                $coupon->increment('used_count');
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
}
