<?php

namespace App\Services;

use App\Models\SaasSetting;
use App\Models\Turf;
use App\Support\IndianStates;
use Illuminate\Support\Facades\Log;

class GstStateResolver
{
    public const GSTIN_REGEX = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i';

    public static function stateFromGstin(?string $gstin): ?string
    {
        if ($gstin === null) {
            return null;
        }

        $trimmed = strtoupper(trim($gstin));
        if (!preg_match(self::GSTIN_REGEX, $trimmed)) {
            return null;
        }

        $prefix = substr($trimmed, 0, 2);
        return IndianStates::isValid($prefix) ? $prefix : null;
    }

    public static function turfStateCode(Turf $turf): ?string
    {
        $setting = $turf->relationLoaded('turfSetting')
            ? $turf->turfSetting
            : ($turf->relationLoaded('setting') ? $turf->setting : $turf->turfSetting);

        if (!$setting) {
            return null;
        }

        $stateCode = $setting->state_code ? trim((string) $setting->state_code) : null;
        if ($stateCode !== null && IndianStates::isValid($stateCode)) {
            return str_pad($stateCode, 2, '0', STR_PAD_LEFT);
        }

        return self::stateFromGstin($setting->gst_number);
    }

    public static function saasStateCode(?SaasSetting $saas): string
    {
        if (!$saas) {
            return '27';
        }

        $stateCode = $saas->state_code ? trim((string) $saas->state_code) : null;
        if ($stateCode !== null && IndianStates::isValid($stateCode)) {
            return str_pad($stateCode, 2, '0', STR_PAD_LEFT);
        }

        $fromGstin = self::stateFromGstin($saas->gst_number);
        if ($fromGstin !== null) {
            return $fromGstin;
        }

        return '27';
    }

    public static function isIntraState(Turf $turf, ?SaasSetting $saas): bool
    {
        $turfState = self::turfStateCode($turf);

        if ($turfState === null) {
            Log::warning('GST: turf has no state; treating as intra-state', [
                'turf_id' => $turf->id,
            ]);
            return true;
        }

        return $turfState === self::saasStateCode($saas);
    }
}
