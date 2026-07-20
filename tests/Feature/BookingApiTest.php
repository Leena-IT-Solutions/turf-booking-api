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

        // 1. Create an upcoming booking (tomorrow)
        $bookingUpcoming = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'additional_discount' => 0.00,
        ]);
        $tomorrowRaw = now()->addDay()->toDateString();
        $tomorrowFormatted = \Carbon\Carbon::parse($tomorrowRaw)->format('F d, Y');
        $bDateUpcoming = $bookingUpcoming->bookingDates()->create([
            'booking_date' => $tomorrowRaw,
            'amount' => 1500,
            'additional_discount' => 0.00,
        ]);
        $bDateUpcoming->bookingSlots()->create([
            'slot_id' => $slot1->id,
        ]);

        // 2. Create a past booking (yesterday)
        $bookingPast = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf->id,
            'date_of_booking' => now()->subDay(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'additional_discount' => 0.00,
        ]);
        $yesterdayRaw = now()->subDay()->toDateString();
        $yesterdayFormatted = \Carbon\Carbon::parse($yesterdayRaw)->format('F d, Y');
        $bDatePast = $bookingPast->bookingDates()->create([
            'booking_date' => $yesterdayRaw,
            'amount' => 2000,
            'additional_discount' => 0.00,
        ]);
        $bDatePast->bookingSlots()->create([
            'slot_id' => $slot1->id,
        ]);

        // Fetch upcoming (default)
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookings?filter=upcoming');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($tomorrowFormatted, $data[0]['booking_date']);
        $this->assertEquals('₹1,500', $data[0]['price']);

        // Fetch past
        $responsePast = $this->actingAs($user, 'sanctum')->getJson('/api/bookings?filter=past');
        $responsePast->assertStatus(200);
        $dataPast = $responsePast->json('data');
        $this->assertCount(1, $dataPast);
        $this->assertEquals($yesterdayFormatted, $dataPast[0]['booking_date']);
        $this->assertEquals('₹2,000', $dataPast[0]['price']);
    }
}
