<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingDate;
use App\Models\Turf;
use App\Services\BookingRescheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingRescheduleController extends Controller
{
    protected BookingRescheduleService $rescheduleService;

    public function __construct(BookingRescheduleService $rescheduleService)
    {
        $this->rescheduleService = $rescheduleService;
    }

    /**
     * Authorize staff access to reschedule this session.
     */
    protected function authorizeStaff(Request $request, BookingDate $bookingDate): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $booking = $bookingDate->booking;
        if (!$booking) {
            abort(404, 'Booking not found.');
        }

        $isSaasAdmin = $user->hasRole('saas-admin');
        $isManageable = $user->hasAnyRole(['turf-admin', 'manager', 'admin']) &&
            Turf::manageable($user)->where('turfs.id', $booking->turf_id)->exists();

        if (!$isSaasAdmin && !$isManageable) {
            abort(403, 'You are not authorized to reschedule this session.');
        }
    }

    /**
     * Get slots on a target date with pricing and availability for rescheduling.
     */
    public function slots(Request $request, BookingDate $bookingDate): JsonResponse
    {
        $this->authorizeStaff($request, $bookingDate);

        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $targetDate = $request->input('date');
        $result = $this->rescheduleService->slots($bookingDate, $targetDate);

        return response()->json($result);
    }

    /**
     * Preview rescheduling calculation and validations without committing.
     */
    public function preview(Request $request, BookingDate $bookingDate): JsonResponse
    {
        $this->authorizeStaff($request, $bookingDate);

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'required|integer',
        ]);

        try {
            $preview = $this->rescheduleService->preview(
                $bookingDate,
                $validated['date'],
                $validated['slot_ids']
            );

            return response()->json($preview);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Confirm and execute rescheduling.
     */
    public function reschedule(Request $request, BookingDate $bookingDate): JsonResponse
    {
        $this->authorizeStaff($request, $bookingDate);

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'required|integer',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $reschedule = $this->rescheduleService->reschedule(
                $bookingDate,
                $validated['date'],
                $validated['slot_ids'],
                $request->user(),
                $validated['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Session rescheduled successfully.',
                'reschedule' => $reschedule,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
