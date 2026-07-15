<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiIconGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_service_throws_exception_if_no_api_key_configured()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(__('Gemini API key is not configured. Please add it in SaaS Settings or .env file.'));

        GeminiService::generateSvgIcon('football');
    }

    public function test_gemini_service_generates_svg_icon_successfully()
    {
        SaasSetting::create([
            'gemini_api_key' => 'dummy_key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'options' => [
                                            [
                                                'word' => 'football',
                                                'svg' => "<svg viewBox='0 0 24 24'><path d='M12 2L2 22h20L12 2z'/></svg>"
                                            ],
                                            [
                                                'word' => 'soccer',
                                                'svg' => "<svg viewBox='0 0 24 24'></svg>"
                                            ],
                                            [
                                                'word' => 'ball',
                                                'svg' => "<svg viewBox='0 0 24 24'></svg>"
                                            ]
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $svg = GeminiService::generateSvgIcon('football');

        $this->assertEquals("<svg viewBox='0 0 24 24'><path d='M12 2L2 22h20L12 2z'/></svg>", $svg);
    }
}
