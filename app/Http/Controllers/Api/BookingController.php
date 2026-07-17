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
        $occupiedSlotIds = Booking::where('turf_id', $turf->id)
            ->whereDate('booking_date', $dateStr)
            ->where('status', 'Confirmed')
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
            ->map(function ($slot) use ($dayOfWeek, $occupiedSlotIds, $wizard, $getRateForTime) {
                // Determine slot price
                $fromTime24 = date('H:i', strtotime($slot->from_time));
                $price = $getRateForTime($wizard, $dayOfWeek, $fromTime24);

                if ($price === null) {
                    $price = isset($slot->pivot->$dayOfWeek) ? (float)$slot->pivot->$dayOfWeek : 1000.00;
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
        ]);

        $userId = auth()->id();
        $slotIds = $validated['slot_ids'];
        $dates = $validated['booking_dates'];
        $bookingType = $validated['booking_type'];

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

        for ($i = 0; $i < count($indices) - 1; $i++) {
            if ($indices[$i + 1] !== $indices[$i] + 1) {
                return response()->json([
                    'message' => "Selected slots must be consecutive.",
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
            $createdBookings = [];
            foreach ($dates as $dateStr) {
                $date = Carbon::parse($dateStr);
                $dayOfWeek = strtolower($date->format('D'));

                foreach ($slotIds as $slotId) {
                    // Check if slot is already booked
                    $alreadyBooked = Booking::where('turf_id', $turf->id)
                        ->whereDate('booking_date', $dateStr)
                        ->where('slot_id', $slotId)
                        ->where('status', 'Confirmed')
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
                    $price = 1000.00;
                    if ($wizard) {
                        $sameWeek = $wizard['sameRateThroughoutWeek'] ?? 'yes';
                        if ($sameWeek === 'yes') {
                            $sameDay = $wizard['sameRateThroughoutDayAll'] ?? 'yes';
                            if ($sameDay === 'yes') {
                                $price = isset($wizard['flatRateAll']) && $wizard['flatRateAll'] !== '' ? (float)$wizard['flatRateAll'] : 1000.00;
                            } else {
                                $ranges = $wizard['timeRangesAll'] ?? [];
                                foreach ($ranges as $range) {
                                    $from = date('H:i', strtotime($range['from'] ?? '00:00'));
                                    $to = date('H:i', strtotime($range['to'] ?? '23:59'));
                                    if ($from > $to) {
                                        if ($fromTime24 >= $from || $fromTime24 < $to) {
                                            $price = ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : 1000.00;
                                            break;
                                        }
                                    } else {
                                        if ($fromTime24 >= $from && $fromTime24 < $to) {
                                            $price = ($range['rate'] ?? '') !== '' ? (float)$range['rate'] : 1000.00;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $price = isset($slot->pivot->$dayOfWeek) ? (float)$slot->pivot->$dayOfWeek : 1000.00;
                    }

                    $booking = Booking::create([
                        'user_id' => $userId,
                        'turf_id' => $turf->id,
                        'slot_id' => $slotId,
                        'booking_date' => $dateStr,
                        'booking_type' => $bookingType,
                        'status' => 'Confirmed',
                        'payment_status' => 'Paid',
                        'price' => $price,
                    ]);

                    $createdBookings[] = $booking;
                }
            }

            \DB::commit();

            return response()->json([
                'message' => 'Turf booked successfully!',
                'bookings' => $createdBookings,
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while booking. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
