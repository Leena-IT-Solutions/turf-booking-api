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
            ->with(['location.user', 'slots', 'facilities', 'turfEquipments', 'sports', 'photos' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get()
            ->map(function ($turf) use ($now, $getRateForTime) {
                return $this->formatTurfData($turf, $now, $getRateForTime);
            });

        return response()->json($turfs);
    }

    public function show(Turf $turf): JsonResponse
    {
        $now = Carbon::now('Asia/Kolkata');
        $turf->loadMissing([
            'location.user',
            'slots',
            'facilities',
            'turfEquipments',
            'sports',
            'photos' => fn($q) => $q->where('is_active', true)
        ]);
        $turf->loadAvg('reviews', 'rating');
        $turf->loadCount('reviews');

        $getRateForTime = function ($wizard, $day, $time) {
            if (!$wizard) return null;
            $sameWeek = $wizard['sameRateThroughoutWeek'] ?? 'yes';
            if ($sameWeek === 'yes') {
                $sameDay = $wizard['sameRateThroughoutDayAll'] ?? 'yes';
                if ($sameDay === 'yes') {
                    return isset($wizard['flatRateAll']) && $wizard['flatRateAll'] !== '' ? (float)$wizard['flatRateAll'] : null;
                }
            }
            return null;
        };

        return response()->json($this->formatTurfData($turf, $now, $getRateForTime));
    }

    /**
     * Resolve a Turf by its QR code (or numeric ID) for mobile deep linking and log an app-open scan.
     */
    public function showByCode(\Illuminate\Http\Request $request, string $code): JsonResponse
    {
        $normalized = strtoupper(trim($code));

        $turf = Turf::where('qr_code', $normalized)
            ->when(ctype_digit($code), fn ($q) => $q->orWhere('id', (int) $code))
            ->firstOrFail();

        \App\Models\TurfQrScan::create([
            'turf_id' => $turf->id,
            'platform' => \App\Models\TurfQrScan::detectPlatform($request->userAgent()),
            'source' => 'app',
            'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 490, ''),
            'ip_hash' => hash('sha256', (string) $request->ip() . '|' . (string) config('app.key')),
            'converted_user_id' => $request->user('sanctum')?->id,
            'converted_at' => $request->user('sanctum') ? now() : null,
        ]);

        return $this->show($turf);
    }

    /**
     * Record user conversion / attribution when a user registers or logs in after scanning a Turf QR code.
     */
    public function recordQrAttribution(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'platform' => 'nullable|string|in:android,ios,desktop',
        ]);

        $normalized = strtoupper(trim($validated['code']));
        $turf = Turf::where('qr_code', $normalized)
            ->when(ctype_digit($validated['code']), fn ($q) => $q->orWhere('id', (int) $validated['code']))
            ->first();

        if (!$turf) {
            return response()->json(['message' => 'Turf not found for code.'], 404);
        }

        $user = $request->user();
        $platform = $validated['platform'] ?? \App\Models\TurfQrScan::detectPlatform($request->userAgent());

        // Attach conversion to latest unattributed scan for this turf within last 24h, or create a new app conversion row
        $latestScan = \App\Models\TurfQrScan::where('turf_id', $turf->id)
            ->whereNull('converted_user_id')
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();

        if ($latestScan) {
            $latestScan->update([
                'converted_user_id' => $user->id,
                'converted_at' => now(),
            ]);
        } else {
            \App\Models\TurfQrScan::create([
                'turf_id' => $turf->id,
                'platform' => $platform,
                'source' => 'app',
                'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 490, ''),
                'ip_hash' => hash('sha256', (string) $request->ip() . '|' . (string) config('app.key')),
                'converted_user_id' => $user->id,
                'converted_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'turf_id' => $turf->id,
            'qr_code' => $turf->qr_code,
        ]);
    }

    private function formatTurfData(Turf $turf, Carbon $now, callable $getRateForTime): array
    {
        $currentDay = strtolower($now->format('D'));
        $currentTime = $now->format('H:i');

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

        $saasMobile = \App\Models\SaasSetting::first()?->contact_mobile;
        $contactNumber = $turf->location?->contact_number 
            ?: ($turf->location?->user?->mobile 
            ?: ($saasMobile ?: ''));

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
            'contact_number' => $contactNumber,
            'whatsapp_number' => $contactNumber,
            'price_text' => $priceText,
            'rating' => $turf->reviews_avg_rating !== null ? number_format($turf->reviews_avg_rating, 1) : '0.0',
            'reviews_count' => $turf->reviews_count,
            'image_url' => count($imageUrls) > 0 ? $imageUrls[0] : null,
            'image_urls' => $imageUrls,
            'sports' => $turf->sports->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'icon' => $s->icon,
            ])->values()->toArray(),
            'facilities' => $turf->facilities->map(fn($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'icon' => $f->icon,
            ])->values()->toArray(),
            'equipments' => $turf->turfEquipments->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'icon' => $e->icon,
            ])->values()->toArray(),
            'is_online_payment_active' => (bool)$turf->is_online_payment_active,
            'is_part_payment_active' => (bool)$turf->is_part_payment_active,
            'is_pay_at_location_active' => (bool)$turf->is_pay_at_location_active,
            'part_payment_type' => $turf->part_payment_type,
            'part_payment_value' => $turf->part_payment_value ? (float)$turf->part_payment_value : null,
            'is_booking_open' => (bool)($turf->is_booking_open ?? true),
            'booking_open_days' => (int)($turf->booking_open_days ?? 30),
            'is_cancellation_active' => (bool)($turf->is_cancellation_active ?? false),
            'cancellation_hours' => (int)($turf->cancellation_hours ?? 0),
            'cancellation_fee' => (float)($turf->cancellation_fee ?? 0.00),
            'cancellation_fee_percentage' => (float)(\App\Models\SaasSetting::first()?->cancellation_fee_percentage ?? 5.00),
            'share_message_template' => $turf->share_message_template,
        ];
    }
}
