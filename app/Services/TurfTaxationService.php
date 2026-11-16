<?php

namespace App\Services;

use App\Models\Turf;
use App\Models\TurfSetting;
use App\Support\IndianStates;

class TurfTaxationService
{
    /**
     * Resolve state name and 2-digit GST state code from either state name or code.
     *
     * @return array{state: string, state_code: ?string}
     */
    public static function resolveState(?string $stateInput): array
    {
        $stateInput = trim((string) $stateInput);
        if ($stateInput === '') {
            return ['state' => '', 'state_code' => null];
        }

        $code = array_search($stateInput, IndianStates::ALL, true);
        if ($code !== false) {
            return ['state' => $stateInput, 'state_code' => (string) $code];
        }

        if (isset(IndianStates::ALL[$stateInput])) {
            return [
                'state' => IndianStates::ALL[$stateInput],
                'state_code' => (string) $stateInput,
            ];
        }

        return ['state' => $stateInput, 'state_code' => null];
    }

    /**
     * Validate GSTIN state prefix against resolved state_code and persist TurfSetting.
     *
     * @param  array<string, mixed>  $validated
     * @return array{ok: bool, field?: string, message?: string, setting?: TurfSetting}
     */
    public static function validateAndSave(Turf $turf, array $validated, ?string $stateCode): array
    {
        if (!IndianStates::isValid($stateCode)) {
            return [
                'ok' => false,
                'field' => 'state',
                'message' => 'Select the state where this turf is located. It decides how GST is charged.',
            ];
        }

        if (!empty($validated['gst_number'])) {
            $validated['gst_number'] = strtoupper(trim((string) $validated['gst_number']));
            $prefix = substr($validated['gst_number'], 0, 2);
            if ($prefix !== $stateCode) {
                $prefixStateName = IndianStates::name($prefix) ?? 'another state';
                $stateName = IndianStates::name($stateCode) ?? ($validated['state'] ?? '');
                return [
                    'ok' => false,
                    'field' => 'gst_number',
                    'message' => "This GSTIN is registered in {$prefixStateName}, but the turf's state is {$stateName}. A turf must use a GSTIN from the state where the venue is.",
                ];
            }
        }

        $validated['state_code'] = $stateCode;
        $validated['state'] = IndianStates::name($stateCode) ?? ($validated['state'] ?? '');

        $setting = TurfSetting::updateOrCreate(
            ['turf_id' => $turf->id],
            $validated
        );

        return [
            'ok' => true,
            'setting' => $setting,
        ];
    }
}

