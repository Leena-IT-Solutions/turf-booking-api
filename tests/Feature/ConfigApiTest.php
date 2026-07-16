<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_endpoint_returns_correct_data(): void
    {
        SaasSetting::create([
            'app_name' => 'CustomTurf',
            'google_maps_api_key' => 'AIzaSyTestKey123',
            'turf_search_km' => 10,
        ]);

        $response = $this->getJson('/api/config');

        $response->assertStatus(200)
            ->assertJson([
                'app_name' => 'CustomTurf',
                'google_maps_api_key' => 'AIzaSyTestKey123',
                'turf_search_km' => 10,
            ]);
    }
}
