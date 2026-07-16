<?php

namespace Tests\Feature;

use App\Models\SliderImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SliderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retrieve_active_slider_images(): void
    {
        // Seed some active and inactive slides
        SliderImage::create([
            'title' => 'Active Slide 1',
            'image_path' => 'sliders/slide1.png',
            'link_url' => 'https://example.com/slide1',
            'order' => 2,
            'is_active' => true,
        ]);

        SliderImage::create([
            'title' => 'Active Slide 2',
            'image_path' => 'sliders/slide2.png',
            'link_url' => 'https://example.com/slide2',
            'order' => 1,
            'is_active' => true,
        ]);

        SliderImage::create([
            'title' => 'Inactive Slide',
            'image_path' => 'sliders/slide3.png',
            'link_url' => 'https://example.com/slide3',
            'order' => 3,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/slider-images');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonPath('0.title', 'Active Slide 2') // Sorted by order
            ->assertJsonPath('1.title', 'Active Slide 1')
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'image_url',
                    'link_url',
                ]
            ]);
    }
}
