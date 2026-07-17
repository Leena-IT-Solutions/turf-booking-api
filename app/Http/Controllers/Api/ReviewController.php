<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /**
     * Display a listing of the reviews for a specific turf.
     */
    public function index(Turf $turf): JsonResponse
    {
        $reviews = $turf->reviews()
            ->with('user')
            ->latest()
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'user_name' => $review->user?->name ?? 'Anonymous User',
                    'rating' => $review->rating,
                    'comment' => $review->comment ?? '',
                    'created_at' => $review->created_at ? $review->created_at->diffForHumans() : 'Just now',
                ];
            });

        return response()->json($reviews);
    }

    /**
     * Store or update a review for a specific turf.
     */
    public function store(Request $request, Turf $turf): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'turf_id' => $turf->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review' => [
                'id' => $review->id,
                'user_name' => auth()->user()->name,
                'rating' => $review->rating,
                'comment' => $review->comment ?? '',
                'created_at' => $review->created_at ? $review->created_at->diffForHumans() : 'Just now',
            ]
        ]);
    }
}
