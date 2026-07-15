<?php

namespace Tests\Feature;

use App\Models\SliderImage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SliderManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/saas/sliders');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_sliders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/sliders');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_sliders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/saas/sliders');
        $response->assertOk();
    }

    public function test_saas_admin_can_create_slider_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('slider1.jpg', 1920, 1080);

        $component = Volt::test('saas.slider-manager')
            ->set('title', 'Summer Promo')
            ->set('link_url', 'https://example.com/summer')
            ->set('order', 1)
            ->set('image', $file)
            ->call('saveSlide');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('slider_images', [
            'title' => 'Summer Promo',
            'link_url' => 'https://example.com/summer',
            'order' => 1,
        ]);

        $slide = SliderImage::first();
        Storage::disk('public')->assertExists($slide->image_path);
    }

    public function test_saas_admin_can_toggle_active_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $this->actingAs($user);

        $slide = SliderImage::create([
            'title' => 'Promo',
            'image_path' => 'sliders/fake.jpg',
            'order' => 0,
            'is_active' => true
        ]);

        $component = Volt::test('saas.slider-manager')
            ->call('toggleActive', $slide->id);

        $this->assertFalse($slide->fresh()->is_active);
    }

    public function test_saas_admin_can_delete_slider_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $this->actingAs($user);

        $path = Storage::disk('public')->putFile('sliders', UploadedFile::fake()->image('slider2.jpg'));

        $slide = SliderImage::create([
            'title' => 'Promo to Delete',
            'image_path' => $path,
            'order' => 2,
            'is_active' => true
        ]);

        Storage::disk('public')->assertExists($path);

        $component = Volt::test('saas.slider-manager')
            ->call('confirmDelete', $slide->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete');

        $this->assertDatabaseMissing('slider_images', [
            'id' => $slide->id
        ]);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_saas_admin_can_reorder_slider_images(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $this->actingAs($user);

        $slide1 = SliderImage::create(['title' => 'Slide 1', 'image_path' => 's1.jpg', 'order' => 0]);
        $slide2 = SliderImage::create(['title' => 'Slide 2', 'image_path' => 's2.jpg', 'order' => 1]);
        $slide3 = SliderImage::create(['title' => 'Slide 3', 'image_path' => 's3.jpg', 'order' => 2]);

        $component = Volt::test('saas.slider-manager')
            ->call('reorderSlides', $slide3->id, $slide1->id);

        $this->assertEquals(0, $slide3->fresh()->order);
        $this->assertEquals(1, $slide1->fresh()->order);
        $this->assertEquals(2, $slide2->fresh()->order);
    }
}
