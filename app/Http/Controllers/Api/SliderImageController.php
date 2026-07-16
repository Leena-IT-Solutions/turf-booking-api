<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SliderImage;
use Illuminate\Http\JsonResponse;

class SliderImageController extends Controller
{
    public function index(): JsonResponse
    {
        $slides = SliderImage::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get()
            ->map(function ($slide) {
                return [
                    'id' => $slide->id,
                    'title' => $slide->title,
                    'image_url' => asset('storage/' . $slide->image_path),
                    'link_url' => $slide->link_url,
                ];
            });

        return response()->json($slides);
    }
}
