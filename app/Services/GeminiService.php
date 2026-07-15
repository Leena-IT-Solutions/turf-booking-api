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
        $options = self::generateSvgIconOptions($concept);
        return $options[0]['svg'] ?? '';
    }

    /**
     * Generate 3 alternative SVG icons for a concept/name using Gemini API.
     *
     * @param string $concept
     * @return array
     * @throws \Exception
     */
    public static function generateSvgIconOptions(string $concept): array
    {
        $setting = SaasSetting::first();
        $apiKey = $setting?->gemini_api_key ?: env('GEMINI_API_KEY');

        if (!$apiKey) {
            throw new \Exception(__('Gemini API key is not configured. Please add it in SaaS Settings or .env file.'));
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Generate 3 alternative SVG icons for the concept \"{$concept}\".\n" .
                                          "The first option should represent the exact concept/name \"{$concept}\".\n" .
                                          "The other two options should represent closely related words or concepts associated with \"{$concept}\".\n\n" .
                                          "Return a JSON object with an \"options\" array, where each item contains:\n" .
                                          "- \"word\": The concept name/word.\n" .
                                          "- \"svg\": Raw clean vector SVG markup. Do not specify fixed width/height. Use viewBox=\"0 0 24 24\", stroke=\"currentColor\", stroke-width=\"2\", fill=\"none\"."
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API call failed: ' . $response->body());
                throw new \Exception(__('Failed to generate icon from Gemini API. Error code: ') . $response->status());
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $data = json_decode($text, true);

            if (!isset($data['options']) || !is_array($data['options'])) {
                throw new \Exception(__('Invalid response format from Gemini API.'));
            }

            // Clean up SVG wrappers if any got returned
            foreach ($data['options'] as &$option) {
                $svg = $option['svg'] ?? '';
                if (preg_match('/```(?:xml|html)?\s*(.*?)\s*```/is', $svg, $matches)) {
                    $svg = $matches[1];
                }
                $svg = trim($svg);

                // Clean up any leading/trailing garbage that might surround the SVG
                if (!str_starts_with($svg, '<svg')) {
                    $startPos = strpos($svg, '<svg');
                    if ($startPos !== false) {
                        $svg = substr($svg, $startPos);
                    }
                }
                if (!str_ends_with($svg, '</svg>')) {
                    $endPos = strrpos($svg, '</svg>');
                    if ($endPos !== false) {
                        $svg = substr($svg, 0, $endPos + 6);
                    }
                }

                $option['svg'] = $svg;
            }

            return $data['options'];

        } catch (\Exception $e) {
            Log::error('Gemini SVG generation exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
