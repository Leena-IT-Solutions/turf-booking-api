<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OfferController extends Controller
{
    /**
     * Display a listing of active coupons/offers.
     */
    public function index()
    {
        $offers = Coupon::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->get();

        return response()->json($offers);
    }

    /**
     * Validate coupon and calculate discount.
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'slots_count' => 'nullable|integer|min:1|max:48',
            'date' => 'nullable|date',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid coupon code.'], 404);
        }

        if (!$coupon->is_active) {
            return response()->json(['message' => 'This coupon is no longer active.'], 422);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return response()->json(['message' => 'This coupon is not yet available.'], 422);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json(['message' => 'This coupon has expired.'], 422);
        }

        // Check day validity
        if ($bookingDate = $request->input('date')) {
            $dateToCheck = Carbon::parse($bookingDate);
            $dayName = strtolower($dateToCheck->format('D'));
            if (in_array($dayName, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']) && !$coupon->$dayName) {
                return response()->json(['message' => 'This coupon is not valid on ' . $dateToCheck->format('l') . 's.'], 422);
            }
        }

        // Check overall usage limit
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['message' => 'This coupon has reached its usage limit.'], 422);
        }

        // Check minimum slots
        if ($coupon->minimum_slots_to_be_ordered !== null) {
            $slotsCount = $request->input('slots_count', 1);
            if ($slotsCount < $coupon->minimum_slots_to_be_ordered) {
                return response()->json([
                    'message' => "You must book at least {$coupon->minimum_slots_to_be_ordered} slots to use this coupon."
                ], 422);
            }
        }

        // Calculate discount
        $discountAmount = 0;
        if (strtolower($coupon->discount_type) === 'percentage') {
            $discountAmount = $request->amount * ($coupon->discount_value / 100);
            if ($coupon->max_discount_amount !== null && $discountAmount > $coupon->max_discount_amount) {
                $discountAmount = $coupon->max_discount_amount;
            }
        } else {
            $discountAmount = $coupon->discount_value;
        }

        // Discount cannot exceed original amount
        if ($discountAmount > $request->amount) {
            $discountAmount = $request->amount;
        }

        return response()->json([
            'message' => 'Coupon applied successfully!',
            'coupon' => $coupon,
            'discount_amount' => (float)number_format($discountAmount, 2, '.', ''),
        ]);
    }
}
