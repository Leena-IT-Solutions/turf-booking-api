<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use Illuminate\Http\JsonResponse;

class TurfController extends Controller
{
    public function index(): JsonResponse
    {
        $turfs = Turf::where('status', 'Approved')
            ->where('is_active', true)
            ->with(['location', 'photos' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get()
            ->map(function ($turf) {
                // Determine a base price or average price from pricing wizard if available
                $priceText = 'N/A';
                if ($turf->pricing_wizard_data) {
                    $wizard = is_array($turf->pricing_wizard_data) 
                        ? $turf->pricing_wizard_data 
                        : json_decode($turf->pricing_wizard_data, true);
                    
                    $rates = [];
                    if (isset($wizard['dayGroups'])) {
                        foreach ($wizard['dayGroups'] as $group) {
                            if (isset($group['flatRate'])) {
                                $rates[] = intval($group['flatRate']);
                            }
                            if (isset($group['timeRanges'])) {
                                foreach ($group['timeRanges'] as $range) {
                                    if (isset($range['rate'])) {
                                        $rates[] = intval($range['rate']);
                                    }
                                }
                            }
                        }
                    }
                    if (!empty($rates)) {
                        $minRate = min($rates);
                        $priceText = '₹' . number_format($minRate) . ' / hr';
                    }
                }

                // If no price found from wizard, default fallback
                if ($priceText === 'N/A') {
                    $priceText = '₹1,000 / hr'; 
                }

                // Get first photo URL or a placeholder
                $imageUrl = null;
                $activePhoto = $turf->photos->first();
                if ($activePhoto) {
                    $imageUrl = asset('storage/' . $activePhoto->photo);
                }

                return [
                    'id' => $turf->id,
                    'name' => $turf->name,
                    'type' => $turf->type,
                    'description' => $turf->description,
                    'area' => $turf->area,
                    'location_name' => $turf->location?->name ?? '',
                    'location_address' => $turf->location?->address ?? '',
                    'price_text' => $priceText,
                    'rating' => '4.8', // Default standard mock rating for UI display
                    'image_url' => $imageUrl,
                ];
            });

        return response()->json($turfs);
    }
}
