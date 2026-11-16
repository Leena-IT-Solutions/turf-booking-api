<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use App\Support\IndianStates;

class TurfOnboardingProgress
{
    /**
     * Evaluate onboarding progress across all 12 steps for a given turf (or location/user if turf is null).
     *
     * @return array{
     *   steps: array<int, array{step:int, key:string, title:string, short:string, required:bool, completed:bool, unlocked:bool, hidden:bool, summary:string}>,
     *   completed_count: int,
     *   total_visible_steps: int,
     *   required_completed: bool,
     *   percentage: int,
     *   first_incomplete_step: int
     * }
     */
    public static function evaluate(?Turf $turf, ?Location $location = null, ?User $actingUser = null): array
    {
        $actingUser = $actingUser ?: auth()->user();
        $isManagerOnly = $actingUser
            && !$actingUser->hasRole('turf-admin')
            && !$actingUser->hasRole('saas-admin')
            && !$actingUser->hasRole('marketing')
            && $actingUser->hasRole('manager');

        if ($turf && !$location) {
            $location = $turf->location;
        }

        // Step 1: Location (requires name, address, and valid lat/lng)
        $hasLocation = $location !== null
            && !empty($location->name)
            && !empty($location->address)
            && $location->latitude !== null
            && $location->longitude !== null;

        // Step 2: Turf
        $hasTurf = $turf !== null && !empty($turf->name) && !empty($turf->type);

        // Step 3: Photos (at least 1 active photo)
        $activePhotosCount = $turf ? $turf->photos()->where('is_active', true)->count() : 0;
        $hasPhotos = $activePhotosCount >= 1;

        // Step 4: Facilities (optional — marked completed once turf exists and has >=1 facility or after reaching Step 5+)
        $facilitiesCount = $turf ? $turf->facilities()->count() : 0;
        $hasFacilities = $facilitiesCount > 0;

        // Step 5: Equipments (optional)
        $equipmentsCount = $turf ? $turf->turfEquipments()->count() : 0;
        $hasEquipments = $equipmentsCount > 0;

        // Step 6: Sports (required >= 1)
        $sportsCount = $turf ? $turf->sports()->count() : 0;
        $hasSports = $sportsCount >= 1;

        // Step 7: Slots (required >= 1 active slot)
        $activeSlots = $turf ? $turf->slots()->wherePivot('is_active', true)->get() : collect();
        $activeSlotsCount = $activeSlots->count();
        $hasSlots = $activeSlotsCount >= 1;

        // Step 8: Pricing (required: all active slots have > 0 prices across mon..sun)
        $hasPricing = false;
        $unpricedCount = 0;
        if ($hasSlots) {
            foreach ($activeSlots as $slot) {
                $missingDay = false;
                foreach (TurfPricingService::DAYS as $day) {
                    $val = $slot->pivot->{$day} ?? null;
                    if ($val === null || (float) $val <= 0) {
                        $missingDay = true;
                        break;
                    }
                }
                if ($missingDay) {
                    $unpricedCount++;
                }
            }
            $hasPricing = ($unpricedCount === 0);
        }

        // Step 9: Taxation (required: valid Indian state_code on TurfSetting)
        $setting = $turf?->turfSetting;
        $hasTaxation = $setting !== null && IndianStates::isValid($setting->state_code);

        // Step 10: Booking Rules (optional — defaults exist on Turf)
        $hasBookingRules = $hasTurf && (
            (bool) $turf->is_online_payment_active
            || (bool) $turf->is_part_payment_active
            || (bool) $turf->is_pay_at_location_active
        );

        // Step 11: Payout Account (optional — hidden for Manager-only users)
        $owner = $turf?->owner() ?? ($location?->user) ?? $actingUser;
        $hasPayout = false;
        if ($owner) {
            $method = $owner->payout_method ?? 'bank';
            $hasPayout = ($method === 'bank' && !empty($owner->bank_account_number) && !empty($owner->bank_ifsc))
                || ($method === 'upi' && !empty($owner->upi_id));
        }

        // Step 12: Review & Submit
        $isSubmittedOrApproved = $turf !== null && in_array($turf->status, ['Pending', 'Approved'], true);

        // Determine progressive unlocking:
        // Step 1 is always unlocked.
        // Step 2 unlocks when Step 1 is done (or location exists).
        // Steps 3..6 unlock once Step 2 (Turf) exists.
        // Step 7 (Slots) unlocks once Step 3 (Photos) and Step 6 (Sports) are done (or if Turf already has slots).
        // Step 8 (Pricing) unlocks once Step 7 (Slots) is done.
        // Steps 9..12 unlock once Step 8 (Pricing) is done (or if Taxation is already done).
        $step2Unlocked = $location !== null;
        $step3To6Unlocked = $hasTurf;
        $step7Unlocked = $hasTurf && ($hasPhotos && $hasSports || $hasSlots);
        $step8Unlocked = $hasTurf && $hasSlots;
        $step9To11Unlocked = $hasTurf && ($hasPricing || $hasTaxation);
        $requiredCompleted = $hasLocation && $hasTurf && $hasPhotos && $hasSports && $hasSlots && $hasPricing && $hasTaxation;
        $step12Unlocked = $requiredCompleted;

        // Optional steps 4 & 5 are considered "completed" in the progress tally once the user has moved past them (e.g. Sports/Slots/Taxation done) or added items
        $step4Completed = $hasFacilities || $hasSports || $hasSlots || $hasTaxation;
        $step5Completed = $hasEquipments || $hasSports || $hasSlots || $hasTaxation;
        $step10Completed = $hasBookingRules && ($hasTaxation || $isSubmittedOrApproved);
        $step11Completed = $hasPayout;

        $steps = [
            1 => [
                'step' => 1,
                'key' => 'location',
                'title' => 'Location',
                'short' => 'Location',
                'required' => true,
                'completed' => $hasLocation,
                'unlocked' => true,
                'hidden' => false,
                'summary' => $hasLocation
                    ? "{$location->name} ({$location->latitude}, {$location->longitude})"
                    : ($location ? 'Missing map pin (lat/lng)' : 'Not configured'),
            ],
            2 => [
                'step' => 2,
                'key' => 'turf',
                'title' => 'Turf Details',
                'short' => 'Turf',
                'required' => true,
                'completed' => $hasTurf,
                'unlocked' => $step2Unlocked,
                'hidden' => false,
                'summary' => $hasTurf ? "{$turf->name} · {$turf->type}" : 'Not configured',
            ],
            3 => [
                'step' => 3,
                'key' => 'photos',
                'title' => 'Photos',
                'short' => 'Photos',
                'required' => true,
                'completed' => $hasPhotos,
                'unlocked' => $step3To6Unlocked,
                'hidden' => false,
                'summary' => $hasPhotos ? "{$activePhotosCount} active photo(s)" : 'Upload at least 1 photo',
            ],
            4 => [
                'step' => 4,
                'key' => 'facilities',
                'title' => 'Facilities',
                'short' => 'Facilities',
                'required' => false,
                'completed' => $step4Completed,
                'unlocked' => $step3To6Unlocked,
                'hidden' => false,
                'summary' => $facilitiesCount > 0 ? "{$facilitiesCount} selected" : 'Optional (0 selected)',
            ],
            5 => [
                'step' => 5,
                'key' => 'equipments',
                'title' => 'Equipments',
                'short' => 'Equipments',
                'required' => false,
                'completed' => $step5Completed,
                'unlocked' => $step3To6Unlocked,
                'hidden' => false,
                'summary' => $equipmentsCount > 0 ? "{$equipmentsCount} selected" : 'Optional (0 selected)',
            ],
            6 => [
                'step' => 6,
                'key' => 'sports',
                'title' => 'Sports',
                'short' => 'Sports',
                'required' => true,
                'completed' => $hasSports,
                'unlocked' => $step3To6Unlocked,
                'hidden' => false,
                'summary' => $hasSports ? "{$sportsCount} sport(s) selected" : 'Select at least 1 sport',
            ],
            7 => [
                'step' => 7,
                'key' => 'slots',
                'title' => 'Slots',
                'short' => 'Slots',
                'required' => true,
                'completed' => $hasSlots,
                'unlocked' => $step7Unlocked,
                'hidden' => false,
                'summary' => $hasSlots ? "{$activeSlotsCount} active slot(s)" : 'Select at least 1 time slot',
            ],
            8 => [
                'step' => 8,
                'key' => 'pricing',
                'title' => 'Pricing',
                'short' => 'Pricing',
                'required' => true,
                'completed' => $hasPricing,
                'unlocked' => $step8Unlocked,
                'hidden' => false,
                'summary' => $hasPricing
                    ? "All {$activeSlotsCount} slot(s) priced (Mon–Sun)"
                    : ($hasSlots ? "{$unpricedCount} slot(s) missing prices" : 'Configure slot prices'),
            ],
            9 => [
                'step' => 9,
                'key' => 'taxation',
                'title' => 'Taxation & GST',
                'short' => 'Taxation',
                'required' => true,
                'completed' => $hasTaxation,
                'unlocked' => $step9To11Unlocked,
                'hidden' => false,
                'summary' => $hasTaxation
                    ? ($setting->state . ($setting->is_gst_billing_active ? " · GSTIN: {$setting->gst_number}" : ' · Non-GST'))
                    : 'Select turf state & GST preferences',
            ],
            10 => [
                'step' => 10,
                'key' => 'booking_rules',
                'title' => 'Booking Rules',
                'short' => 'Rules',
                'required' => false,
                'completed' => $step10Completed,
                'unlocked' => $step9To11Unlocked,
                'hidden' => false,
                'summary' => $hasTurf
                    ? ($turf->booking_open_days . 'd window · ' . ($turf->is_cancellation_active ? 'Cancellations ON' : 'No cancellations'))
                    : 'Default booking & payment rules',
            ],
            11 => [
                'step' => 11,
                'key' => 'payout',
                'title' => 'Payout Account',
                'short' => 'Payout',
                'required' => false,
                'completed' => $step11Completed,
                'unlocked' => $step9To11Unlocked,
                'hidden' => $isManagerOnly,
                'summary' => $hasPayout
                    ? (strtoupper($owner->payout_method ?? 'bank') . ' configured')
                    : 'Optional (needed for online payouts)',
            ],
            12 => [
                'step' => 12,
                'key' => 'review',
                'title' => 'Review & Submit',
                'short' => 'Review',
                'required' => true,
                'completed' => $isSubmittedOrApproved && $requiredCompleted,
                'unlocked' => $step12Unlocked,
                'hidden' => false,
                'summary' => $turf ? "Status: {$turf->status}" : 'Review & submit for verification',
            ],
        ];

        $visibleSteps = array_filter($steps, fn ($s) => !$s['hidden']);
        $completedCount = count(array_filter($visibleSteps, fn ($s) => $s['completed']));
        $totalVisible = count($visibleSteps);
        $percentage = $totalVisible > 0 ? (int) round(($completedCount / $totalVisible) * 100) : 0;

        // First incomplete required step (or Step 12 if all required steps 1..9 are done)
        $firstIncomplete = 12;
        foreach ([1, 2, 3, 6, 7, 8, 9] as $reqStep) {
            if (!$steps[$reqStep]['completed']) {
                $firstIncomplete = $reqStep;
                break;
            }
        }

        return [
            'steps' => $steps,
            'completed_count' => $completedCount,
            'total_visible_steps' => $totalVisible,
            'required_completed' => $requiredCompleted,
            'percentage' => $percentage,
            'first_incomplete_step' => $firstIncomplete,
        ];
    }
}
