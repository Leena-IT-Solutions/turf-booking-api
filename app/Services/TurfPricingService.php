<?php

namespace App\Services;

use App\Models\Slot;
use App\Models\Turf;

class TurfPricingService
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Check if a slot's start time falls within a time range.
     */
    public static function slotMatchesRange(string $slotFrom, string $slotTo, string $rangeFrom, string $rangeTo): bool
    {
        if ($rangeFrom === '' || $rangeTo === '') {
            return false;
        }

        $slotStart = date('H:i', strtotime($slotFrom));
        $from = strlen($rangeFrom) === 5 ? $rangeFrom : date('H:i', strtotime($rangeFrom));
        $to   = strlen($rangeTo) === 5 ? $rangeTo : date('H:i', strtotime($rangeTo));

        // Full 24h range (00:00 -> 23:59) covers every slot
        if ($from === '00:00' && ($to === '23:59' || $to === '24:00')) {
            return true;
        }

        // Overnight range (e.g. 22:00 -> 06:00)
        if ($from > $to) {
            return ($slotStart >= $from || $slotStart < $to);
        }

        return ($slotStart >= $from && $slotStart < $to);
    }

    /**
     * Compute per-slot, per-day scaled prices for a turf from wizard parameters without writing to DB.
     *
     * @return array<int, array<string, float|null>> Map of slot_id => ['mon' => ?, ..., 'sun' => ?]
     */
    public static function computePrices(
        iterable $activeSlots,
        string $sameRateThroughoutWeek,
        string $sameRateThroughoutDayAll,
        mixed $flatRateAll,
        array $timeRangesAll,
        array $dayGroups
    ): array {
        $result = [];

        foreach ($activeSlots as $slot) {
            $prices = [
                'mon' => null, 'tue' => null, 'wed' => null, 'thu' => null,
                'fri' => null, 'sat' => null, 'sun' => null,
            ];

            $scaleFactor = ((float) $slot->duration) / 60.0;

            if ($sameRateThroughoutWeek === 'yes') {
                if ($sameRateThroughoutDayAll === 'yes') {
                    if ($flatRateAll !== '' && $flatRateAll !== null) {
                        $rate = (float) $flatRateAll;
                        $scaledRate = round($rate * $scaleFactor, 2);
                        foreach (self::DAYS as $day) {
                            $prices[$day] = $scaledRate;
                        }
                    }
                } else {
                    $scaledRate = null;
                    foreach ($timeRangesAll as $range) {
                        if (self::slotMatchesRange((string) $slot->from_time, (string) $slot->to_time, (string) ($range['from'] ?? ''), (string) ($range['to'] ?? ''))) {
                            if (($range['rate'] ?? '') !== '' && ($range['rate'] ?? null) !== null) {
                                $rate = (float) $range['rate'];
                                $scaledRate = round($rate * $scaleFactor, 2);
                            }
                            break;
                        }
                    }
                    foreach (self::DAYS as $day) {
                        $prices[$day] = $scaledRate;
                    }
                }
            } else {
                foreach ($dayGroups as $group) {
                    $selectedDays = $group['days'] ?? [];
                    if (empty($selectedDays)) {
                        continue;
                    }

                    if (($group['sameRateThroughoutDay'] ?? 'yes') === 'yes') {
                        if (($group['flatRate'] ?? '') !== '' && ($group['flatRate'] ?? null) !== null) {
                            $rate = (float) $group['flatRate'];
                            $scaledRate = round($rate * $scaleFactor, 2);
                            foreach ($selectedDays as $day) {
                                if (in_array($day, self::DAYS, true)) {
                                    $prices[$day] = $scaledRate;
                                }
                            }
                        }
                    } else {
                        $scaledRate = null;
                        $groupRanges = $group['timeRanges'] ?? [];
                        foreach ($groupRanges as $range) {
                            if (self::slotMatchesRange((string) $slot->from_time, (string) $slot->to_time, (string) ($range['from'] ?? ''), (string) ($range['to'] ?? ''))) {
                                if (($range['rate'] ?? '') !== '' && ($range['rate'] ?? null) !== null) {
                                    $rate = (float) $range['rate'];
                                    $scaledRate = round($rate * $scaleFactor, 2);
                                }
                                break;
                            }
                        }
                        foreach ($selectedDays as $day) {
                            if (in_array($day, self::DAYS, true)) {
                                $prices[$day] = $scaledRate;
                            }
                        }
                    }
                }
            }

            $result[$slot->id] = $prices;
        }

        return $result;
    }

    /**
     * Find any active slots that have null or <= 0 price on any day in a computed price map.
     *
     * @return array<int, array{slot_id:int, label:string, missing_days:array<int,string>}>
     */
    public static function findUnpricedSlots(iterable $activeSlots, array $computedPrices): array
    {
        $unpriced = [];

        foreach ($activeSlots as $slot) {
            $prices = $computedPrices[$slot->id] ?? [];
            $missingDays = [];
            foreach (self::DAYS as $day) {
                $val = $prices[$day] ?? null;
                if ($val === null || (float) $val <= 0) {
                    $missingDays[] = strtoupper($day);
                }
            }

            if (!empty($missingDays)) {
                $unpriced[] = [
                    'slot_id' => $slot->id,
                    'label' => date('h:i A', strtotime($slot->from_time)) . ' - ' . date('h:i A', strtotime($slot->to_time)),
                    'missing_days' => $missingDays,
                ];
            }
        }

        return $unpriced;
    }

    /**
     * Apply pricing wizard rules to a turf and its active slots.
     */
    public static function applyPricingToTurf(
        Turf $turf,
        string $sameRateThroughoutWeek,
        string $sameRateThroughoutDayAll,
        mixed $flatRateAll,
        array $timeRangesAll,
        array $dayGroups,
        bool $attachMissingMasterSlots = false
    ): void {
        $turf->update([
            'pricing_wizard_data' => [
                'sameRateThroughoutWeek' => $sameRateThroughoutWeek,
                'sameRateThroughoutDayAll' => $sameRateThroughoutDayAll,
                'flatRateAll' => $flatRateAll,
                'timeRangesAll' => $timeRangesAll,
                'dayGroups' => $dayGroups,
            ],
        ]);

        if ($attachMissingMasterSlots) {
            $allActiveSlots = Slot::where('is_active', true)->pluck('id');
            $alreadyAttached = $turf->slots()->pluck('slots.id');
            $toAttach = $allActiveSlots->diff($alreadyAttached);

            if ($toAttach->isNotEmpty()) {
                $attachData = [];
                foreach ($toAttach as $slotId) {
                    $attachData[$slotId] = ['is_active' => true];
                }
                $turf->slots()->attach($attachData);
            }
        }

        $activeSlots = $turf->slots()->wherePivot('is_active', true)->get();
        $computed = self::computePrices(
            $activeSlots,
            $sameRateThroughoutWeek,
            $sameRateThroughoutDayAll,
            $flatRateAll,
            $timeRangesAll,
            $dayGroups
        );

        foreach ($activeSlots as $slot) {
            $prices = $computed[$slot->id] ?? [
                'mon' => null, 'tue' => null, 'wed' => null, 'thu' => null,
                'fri' => null, 'sat' => null, 'sun' => null,
            ];
            $turf->slots()->updateExistingPivot($slot->id, $prices);
        }
    }
}
