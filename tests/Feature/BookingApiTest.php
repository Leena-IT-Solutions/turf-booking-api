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

    public function test_booking_preview_and_manager_record_payment(): void
    {
        $admin = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['name' => 'turf-admin'], ['display_name' => 'Turf Admin']);
        $admin->roles()->sync([$role->id]);

        $customer = User::factory()->create();

        $location = Location::create([
            'user_id' => $admin->id,
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
            'from_time' => '06:00:00',
            'to_time' => '07:00:00',
            'duration' => 60,
            'price' => 1000,
            'is_active' => true,
        ]);

        $slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '07:00:00',
            'to_time' => '08:00:00',
            'duration' => 60,
            'price' => 1200,
            'is_active' => true,
        ]);

        $turf->slots()->attach([$slot1->id => ['is_active' => true], $slot2->id => ['is_active' => true]]);

        $dates = [now()->addDay()->toDateString(), now()->addDays(2)->toDateString()];

        // Test preview endpoint
        $previewResponse = $this->actingAs($customer, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings/preview", [
            'slot_ids' => [$slot1->id, $slot2->id],
            'booking_dates' => $dates,
            'booking_type' => 'long',
        ]);

        $previewResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'subtotal',
                'total_amount',
                'dates'
            ]);

        // Test Manager booking on behalf of another user
        $storeResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings", [
            'slot_ids' => [$slot1->id, $slot2->id],
            'booking_dates' => $dates,
            'booking_type' => 'long',
            'payment_method' => 'offline',
            'amount_received' => 1000,
            'customer_id' => $customer->id,
        ]);

        $storeResponse->assertStatus(200);

        // Fetch bookings as admin
        $bookingsResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/bookings');
        $bookingsResponse->assertStatus(200);
        $bookingsData = $bookingsResponse->json('data');
        
        $this->assertNotEmpty($bookingsData);
        $firstBookingDateId = $bookingsData[0]['id'];

        // Test Manager recording daily offline payment
        $paymentResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/booking-dates/{$firstBookingDateId}/payments", [
            'payment_method' => 'Cash',
            'amount' => 500,
        ]);

        $paymentResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment recorded successfully.'
            ]);
    }

    public function test_get_slots_with_multiple_dates(): void
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
        $slot = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '06:00:00',
            'to_time' => '07:00:00',
            'duration' => 60,
            'price' => 1000,
            'is_active' => true,
        ]);
        $turf->slots()->attach([$slot->id => ['is_active' => true]]);

        $tomorrow = now()->addDay()->toDateString();
        $dayAfter = now()->addDays(2)->toDateString();

        $booking = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'additional_discount' => 0.00,
        ]);
        $bDate = $booking->bookingDates()->create([
            'booking_date' => $dayAfter,
            'amount' => 1000,
            'additional_discount' => 0.00,
        ]);
        $bDate->bookingSlots()->create([
            'slot_id' => $slot->id,
        ]);

        $response = $this->getJson("/api/turfs/{$turf->id}/slots?dates[]={$tomorrow}&dates[]={$dayAfter}");
        $response->assertStatus(200);
        
        $slots = $response->json();
        $this->assertNotEmpty($slots);
        $this->assertTrue($slots[0]['is_booked']);
    }

    public function test_booking_with_payment_option_full_on_part_payment_active(): void
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
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
        ]);
        $category = SlotCategory::create([
            'name' => 'Morning',
            'is_active' => true,
        ]);
        $slot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '06:00:00',
            'to_time' => '07:00:00',
            'duration' => 60,
            'price' => 1000,
            'is_active' => true,
        ]);
        $slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '07:00:00',
            'to_time' => '08:00:00',
            'duration' => 60,
            'price' => 800,
            'is_active' => true,
        ]);
        $turf->slots()->attach([
            $slot1->id => ['is_active' => true],
            $slot2->id => ['is_active' => true],
        ]);

        $tomorrow = now()->addDay()->toDateString();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings", [
            'slot_ids' => [$slot1->id, $slot2->id],
            'booking_dates' => [$tomorrow],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'razorpay_payment_id' => 'pay_12345full',
        ]);

        $response->assertStatus(200);
        $booking = Booking::with('payments')->find($response->json('booking.id'));
        $this->assertEquals(2000, $booking->payments->sum('amount'));
        $this->assertEquals('Paid', $booking->payment_status);

        $tomorrow2 = now()->addDays(2)->toDateString();
        $response2 = $this->actingAs($user, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings", [
            'slot_ids' => [$slot1->id, $slot2->id],
            'booking_dates' => [$tomorrow2],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'part',
            'razorpay_payment_id' => 'pay_12345part',
        ]);

        $response2->assertStatus(200);
        $booking2 = Booking::with('payments')->find($response2->json('booking.id'));
        $this->assertEquals(1000, $booking2->payments->sum('amount'));
        $this->assertEquals('Partially Paid', $booking2->payment_status);
    }
}
