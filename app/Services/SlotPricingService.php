<?php

namespace App\Services;

use App\Models\Slot;
use App\Models\Turf;
use Carbon\Carbon;

class SlotPricingService
{
    /**
     * Compute the exact price for a slot on a given date for a turf.
     * Checks Pricing Wizard -> slot_turf pivot day rate -> ₹1000/hr fallback rate.
     */
    public static function priceForSlot(Turf $turf, Slot $slotWithPivot, string $dateYmd): float
    {
        $dayOfWeek = strtolower(Carbon::parse($dateYmd)->format('D'));
        $fromTime24 = date('H:i', strtotime($slotWithPivot->from_time));
        $duration = intval($slotWithPivot->duration ?: 30);

        $wizard = is_array($turf->pricing_wizard_data)
            ? $turf->pricing_wizard_data
            : (is_string($turf->pricing_wizard_data) ? json_decode($turf->pricing_wizard_data, true) : null);

        $hourlyRate = self::getRateForTime($wizard, $dayOfWeek, $fromTime24);

        if ($hourlyRate !== null) {
            return round(($hourlyRate / 60) * $duration, 2);
        }

        if (isset($slotWithPivot->pivot->$dayOfWeek)) {
            return (float) $slotWithPivot->pivot->$dayOfWeek;
        }

        return round((1000.00 / 60) * $duration, 2);
    }

    /**
     * Determine hourly rate from the pricing wizard JSON structure.
     */
    public static function getRateForTime(?array $wizard, string $day, string $time): ?float
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
