<?php

namespace App\Services;

use App\Models\SaasSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Generate raw SVG markup for a concept using Gemini API.
     *
     * @param string $concept
     * @return string
     * @throws \Exception
     */
    public static function generateSvgIcon(string $concept): string
    {
        $setting = SaasSetting::first();
        $apiKey = $setting?->gemini_api_key ?: env('GEMINI_API_KEY');

        if (!$apiKey) {
            throw new \Exception(__('Gemini API key is not configured. Please add it in SaaS Settings or .env file.'));
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Generate a single clean valid SVG icon vector for the concept: '{$concept}'.\n\n" .
                                          "Requirements:\n" .
                                          "1. Output ONLY the raw SVG code. No markdown code blocks (no ```xml or ```html), no commentary, no HTML wrapper.\n" .
                                          "2. Use a stroke-width of 2, stroke='currentColor', fill='none' (or fill='currentColor' only where appropriate).\n" .
                                          "3. Do not specify fixed width or height, instead use viewBox='0 0 24 24'.\n" .
                                          "4. Output must start with '<svg' and end with '</svg>'."
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API call failed: ' . $response->body());
                throw new \Exception(__('Failed to generate icon from Gemini API. Error code: ') . $response->status());
            }

            $result = $response->json();
            $svgText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Strip any markdown code block wrappers if Gemini ignored instructions
            if (preg_match('/```(?:xml|html)?\s*(.*?)\s*```/is', $svgText, $matches)) {
                $svgText = $matches[1];
            }

            $svgText = trim($svgText);

            // Clean up any leading/trailing garbage that might surround the SVG
            if (!str_starts_with($svgText, '<svg')) {
                $startPos = strpos($svgText, '<svg');
                if ($startPos !== false) {
                    $svgText = substr($svgText, $startPos);
                }
            }
            if (!str_ends_with($svgText, '</svg>')) {
                $endPos = strrpos($svgText, '</svg>');
                if ($endPos !== false) {
                    $svgText = substr($svgText, 0, $endPos + 6);
                }
            }

            if (!str_starts_with($svgText, '<svg') || !str_ends_with($svgText, '</svg>')) {
                throw new \Exception(__('Invalid SVG format returned by Gemini API.'));
            }

            return $svgText;

        } catch (\Exception $e) {
            Log::error('Gemini SVG generation exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
