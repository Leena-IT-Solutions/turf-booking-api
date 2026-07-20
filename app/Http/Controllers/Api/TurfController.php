<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TurfController extends Controller
{
    public function index(): JsonResponse
    {
        // Get the current day and time in the Indian timezone (Asia/Kolkata)
        $now = Carbon::now('Asia/Kolkata');
        $currentDay = strtolower($now->format('D')); // 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'
        $currentTime = $now->format('H:i');

        $getRateForTime = function ($wizard, $day, $time) {
            if (!$wizard) {
                return null;
            }

            $sameWeek = $wizard['sameRateThroughoutWeek'] ?? 'yes';
            
            if ($sameWeek === 'yes') {
                $sameDay = $wizard['sameRateThroughoutDayAll'] ?? 'yes';
                if ($sameDay === 'yes') {
                    return isset($wizard['flatRateAll']) && $wizard['flatRateAll'] !== '' 
                        ? (float)$wizard['flatRateAll'] 
                        : null;
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
                            return isset($group['flatRate']) && $group['flatRate'] !== '' 
                                ? (float)$group['flatRate'] 
                                : null;
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

        $turfs = Turf::where('status', 'Approved')
            ->where('is_active', true)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->with(['location', 'slots', 'facilities', 'turfEquipments', 'sports', 'photos' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get()
            ->map(function ($turf) use ($currentDay, $currentTime, $getRateForTime) {
                $wizard = is_array($turf->pricing_wizard_data) 
                    ? $turf->pricing_wizard_data 
                    : json_decode($turf->pricing_wizard_data, true);

                // 1. Try to get current time rate from pricing wizard
                $rate = $getRateForTime($wizard, $currentDay, $currentTime);

                // 2. Fall back to active slot pivot rate if wizard is not set or doesn't match
                if ($rate === null) {
                    $activeSlot = $turf->slots->first(function ($slot) use ($currentTime) {
                        $from = date('H:i', strtotime($slot->from_time));
                        $to = date('H:i', strtotime($slot->to_time));
                        if ($from > $to) {
                            return ($currentTime >= $from || $currentTime < $to);
                        }
                        return ($currentTime >= $from && $currentTime < $to);
                    });

                    if ($activeSlot && isset($activeSlot->pivot->$currentDay)) {
                        $slotPrice = (float)$activeSlot->pivot->$currentDay;
                        $duration = intval($activeSlot->duration ?: 60);
                        if ($duration > 0) {
                            $rate = ($slotPrice / $duration) * 60;
                        }
                    }
                }

                // 3. Set formatted price text (with standard 1,000 fallback if no rate exists)
                if ($rate === null || $rate <= 0) {
                    $priceText = '₹1,000 / hr'; 
                } else {
                    $priceText = '₹' . number_format($rate) . ' / hr';
                }

                // Get all photo URLs
                $imageUrls = $turf->photos->map(function ($p) {
                    return asset('storage/' . $p->photo);
                })->toArray();

                return [
                    'id' => $turf->id,
                    'name' => $turf->name,
                    'type' => $turf->type,
                    'description' => $turf->description ?? 'No description provided.',
                    'area' => $turf->area,
                    'location_name' => $turf->location?->name ?? '',
                    'location_address' => $turf->location?->address ?? '',
                    'latitude' => $turf->location?->latitude ? (float)$turf->location->latitude : null,
                    'longitude' => $turf->location?->longitude ? (float)$turf->location->longitude : null,
                    'price_text' => $priceText,
                    'rating' => $turf->reviews_avg_rating !== null ? number_format($turf->reviews_avg_rating, 1) : '0.0',
                    'reviews_count' => $turf->reviews_count,
                    'image_url' => count($imageUrls) > 0 ? $imageUrls[0] : null,
                    'image_urls' => $imageUrls,
                    'sports' => $turf->sports->pluck('name')->toArray(),
                    'facilities' => $turf->facilities->pluck('name')->toArray(),
                    'equipments' => $turf->turfEquipments->pluck('name')->toArray(),
                    'is_online_payment_active' => $turf->is_online_payment_active,
                    'is_part_payment_active' => $turf->is_part_payment_active,
                    'is_pay_at_location_active' => $turf->is_pay_at_location_active,
                    'part_payment_type' => $turf->part_payment_type,
                    'part_payment_value' => $turf->part_payment_value ? (float)$turf->part_payment_value : null,
                    'cancellation_hours' => $turf->cancellation_hours,
                    'cancellation_fee' => (float)$turf->cancellation_fee,
                    'share_message_template' => $turf->share_message_template,
                ];
            });

        return response()->json($turfs);
    }
}
