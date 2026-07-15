<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('customer');
    }

    public function test_can_list_active_offers_publicly(): void
    {
        // Create an active coupon
        Coupon::create([
            'code' => 'ACTIVE10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        // Create an inactive coupon
        Coupon::create([
            'code' => 'INACTIVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        // Create an expired coupon
        Coupon::create([
            'code' => 'EXPIRED30',
            'discount_type' => 'percentage',
            'discount_value' => 30,
            'is_active' => true,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/offers');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['code' => 'ACTIVE10'])
            ->assertJsonMissing(['code' => 'INACTIVE20'])
            ->assertJsonMissing(['code' => 'EXPIRED30']);
    }

    public function test_guest_cannot_validate_coupon(): void
    {
        $response = $this->postJson('/api/coupons/validate', [
            'code' => 'TESTCODE',
            'amount' => 500,
        ]);

        $response->assertStatus(401);
    }

    public function test_validate_percentage_coupon(): void
    {
        Coupon::create([
            'code' => 'PERCENT20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'max_discount_amount' => 150,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        // Scenario A: Under max limit (20% of 500 = 100)
        $response = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'PERCENT20',
                'amount' => 500,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'discount_amount' => 100.00,
            ]);

        // Scenario B: Exceeding max limit (20% of 1000 = 200, capped at 150)
        $response = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'PERCENT20',
                'amount' => 1000,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'discount_amount' => 150.00,
            ]);
    }

    public function test_validate_fixed_coupon(): void
    {
        Coupon::create([
            'code' => 'FIXED100',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'FIXED100',
                'amount' => 500,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'discount_amount' => 100.00,
            ]);

        // Capped at total amount
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'FIXED100',
                'amount' => 50,
            ]);

        $response2->assertStatus(200)
            ->assertJsonFragment([
                'discount_amount' => 50.00,
            ]);
    }

    public function test_validate_coupon_weekday_restriction(): void
    {
        // Active, but not valid on Mon/Wed
        Coupon::create([
            'code' => 'TUESDAYONLY',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'mon' => false,
            'tue' => true,
            'wed' => false,
        ]);

        // Check on Monday (2026-07-20 is a Monday)
        $response = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'TUESDAYONLY',
                'amount' => 500,
                'date' => '2026-07-20',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'This coupon is not valid on Mondays.',
            ]);

        // Check on Tuesday (2026-07-21 is a Tuesday)
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'TUESDAYONLY',
                'amount' => 500,
                'date' => '2026-07-21',
            ]);

        $response2->assertStatus(200)
            ->assertJsonFragment([
                'discount_amount' => 50.00,
            ]);
    }

    public function test_validate_coupon_min_slots_restriction(): void
    {
        Coupon::create([
            'code' => 'MINSLOTS3',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'minimum_slots_to_be_ordered' => 3,
        ]);

        // 2 slots should fail
        $response = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'MINSLOTS3',
                'amount' => 500,
                'slots_count' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'You must book at least 3 slots to use this coupon.',
            ]);

        // 3 slots should pass
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/coupons/validate', [
                'code' => 'MINSLOTS3',
                'amount' => 500,
                'slots_count' => 3,
            ]);

        $response2->assertStatus(200);
    }
}
