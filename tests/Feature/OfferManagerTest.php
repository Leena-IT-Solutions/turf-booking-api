<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OfferManagerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('turf-admin');
    }

    public function test_can_view_offers_list_and_search(): void
    {
        $this->actingAs($this->admin);

        Coupon::create([
            'code' => 'DISCOUNT50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Coupon::create([
            'code' => 'WINTER100',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $component = Volt::test('turf.offer-manager')
            ->assertSee('DISCOUNT50')
            ->assertSee('WINTER100');

        $component->set('search', 'WINTER')
            ->assertSee('WINTER100')
            ->assertDontSee('DISCOUNT50');
    }

    public function test_can_create_coupon(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.offer-manager')
            ->call('openCreateModal')
            ->set('code', 'NEWYEAR20')
            ->set('discount_type', 'percentage')
            ->set('discount_value', 20)
            ->set('starts_at', now()->format('Y-m-d\TH:i'))
            ->set('expires_at', now()->addMonth()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('coupons', [
            'code' => 'NEWYEAR20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
        ]);
    }

    public function test_can_edit_coupon(): void
    {
        $this->actingAs($this->admin);

        $coupon = Coupon::create([
            'code' => 'SUMMER10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Volt::test('turf.offer-manager')
            ->call('openEditModal', $coupon->id)
            ->assertSet('code', 'SUMMER10')
            ->set('discount_value', 15)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertEquals(15.00, $coupon->fresh()->discount_value);
    }

    public function test_can_toggle_coupon_active_status(): void
    {
        $this->actingAs($this->admin);

        $coupon = Coupon::create([
            'code' => 'SPRING5',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Volt::test('turf.offer-manager')
            ->call('toggleActive', $coupon->id);

        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_can_delete_coupon(): void
    {
        $this->actingAs($this->admin);

        $coupon = Coupon::create([
            'code' => 'EXPIRED90',
            'discount_type' => 'percentage',
            'discount_value' => 90,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Volt::test('turf.offer-manager')
            ->call('deleteCoupon', $coupon->id);

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }
}
