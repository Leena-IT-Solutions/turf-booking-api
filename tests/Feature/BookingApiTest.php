<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Location;
use App\Models\Turf;
use App\Models\SlotCategory;
use App\Models\Slot;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_access_bookings_without_authentication(): void
    {
        $response = $this->getJson('/api/bookings');
        $response->assertStatus(401);
    }

    public function test_can_fetch_own_bookings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $location = Location::create([
            'user_id' => $user->id,
            'name' => 'Mumbai Arena',
            'address' => 'Ghatkopar East',
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Legends Turf',
            'type' => 'Synthetic',
        ]);

        $category = SlotCategory::create([
            'name' => 'Morning',
            'is_active' => true,
        ]);
        
        $slot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
            'price' => 1500,
            'is_active' => true,
        ]);
        
        $slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '20:00:00',
            'to_time' => '21:00:00',
            'duration' => 60,
            'price' => 2000,
            'is_active' => true,
        ]);

        // Create booking for user
        $booking1 = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf->id,
            'slot_id' => $slot1->id,
            'booking_date' => '2026-07-20',
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'price' => 1500,
        ]);

        // Create booking for another user
        Booking::create([
            'user_id' => $otherUser->id,
            'turf_id' => $turf->id,
            'slot_id' => $slot2->id,
            'booking_date' => '2026-07-21',
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'price' => 2000,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'turf_name' => 'Legends Turf',
                'date' => 'July 20, 2026',
                'time' => '06:00 PM - 07:00 PM',
                'status' => 'Confirmed',
                'price' => '₹1,500',
            ]);
    }
}
