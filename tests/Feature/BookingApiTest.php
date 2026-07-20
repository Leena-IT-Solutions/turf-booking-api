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

    public function test_booking_past_slot_for_today_is_blocked(): void
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
        
        // Define two slots in the past
        $pastSlot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '00:00:00',
            'to_time' => '01:00:00',
            'duration' => 60,
            'price' => 1000,
            'is_active' => true,
        ]);
        $pastSlot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '01:00:00',
            'to_time' => '02:00:00',
            'duration' => 60,
            'price' => 1000,
            'is_active' => true,
        ]);
        $turf->slots()->attach([
            $pastSlot1->id => ['is_active' => true],
            $pastSlot2->id => ['is_active' => true],
        ]);

        $today = \Carbon\Carbon::today('Asia/Kolkata')->toDateString();

        // Acting as user, attempt to book these slots for today
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings", [
            'slot_ids' => [$pastSlot1->id, $pastSlot2->id],
            'booking_dates' => [$today],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'razorpay_payment_id' => 'pay_past_slot',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot book a past slot.');
    }

    public function test_get_coupons_for_turf(): void
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

        // Active coupon
        \App\Models\Coupon::create([
            'turf_id' => $turf->id,
            'code' => 'SAVE10',
            'description' => 'Get 10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        // Inactive coupon
        \App\Models\Coupon::create([
            'turf_id' => $turf->id,
            'code' => 'SAVE50',
            'description' => 'Get 50% off',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'is_active' => false,
        ]);

        // Expired coupon
        \App\Models\Coupon::create([
            'turf_id' => $turf->id,
            'code' => 'EXPIRED',
            'description' => 'Expired coupon',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $response = $this->getJson("/api/turfs/{$turf->id}/coupons");
        $response->assertStatus(200);

        $coupons = $response->json();
        $this->assertCount(1, $coupons);
        $this->assertEquals('SAVE10', $coupons[0]['code']);
    }

    public function test_manager_booking_on_behalf_with_offline_payment_methods(): void
    {
        $manager = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $manager->roles()->sync([$role->id]);

        $customer = User::factory()->create();

        $location = Location::create([
            'user_id' => $manager->id,
            'name' => 'Mumbai Arena',
            'address' => 'Ghatkopar East',
        ]);
        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Legends Turf',
            'type' => 'Synthetic',
            'hourly_rate' => 1000,
        ]);
        $category = SlotCategory::create(['name' => 'General']);
        $slot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '10:00:00',
            'to_time' => '11:00:00',
            'duration' => 60,
            'price' => 500,
            'is_active' => true,
        ]);
        $slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '11:00:00',
            'to_time' => '12:00:00',
            'duration' => 60,
            'price' => 500,
            'is_active' => true,
        ]);
        $turf->slots()->attach([
            $slot1->id => ['is_active' => true],
            $slot2->id => ['is_active' => true],
        ]);

        $tomorrow = \Carbon\Carbon::tomorrow('Asia/Kolkata')->toDateString();

        // Perform booking
        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings", [
            'slot_ids' => [$slot1->id, $slot2->id],
            'booking_dates' => [$tomorrow],
            'booking_type' => 'day',
            'payment_method' => 'Cash',
            'customer_id' => $customer->id,
            'amount_received' => 2000,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $customer->id,
            'payment_status' => 'Paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'amount' => 2000,
            'payment_method' => 'Cash',
            'status' => 'Success',
        ]);
    }

    public function test_quick_create_user_and_register_override(): void
    {
        $manager = User::factory()->create();
        $roleManager = \App\Models\Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $manager->roles()->sync([$roleManager->id]);

        $roleCustomer = \App\Models\Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer']);

        // 1. Quick create by manager
        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/users/quick-create", [
            'name' => 'Quick Customer',
            'email' => 'quick@gmail.com',
            'mobile' => '9876543210',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'Quick Customer');
        $response->assertJsonPath('is_quick_created', true);

        $this->assertDatabaseHas('users', [
            'email' => 'quick@gmail.com',
            'mobile' => '9876543210',
            'is_quick_created' => true,
        ]);

        // 2. User registers using the same email/mobile
        $registerResponse = $this->postJson("/api/register", [
            'name' => 'Quick Customer Registered',
            'email' => 'quick@gmail.com',
            'mobile' => '9876543210',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $registerResponse->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'quick@gmail.com',
            'mobile' => '9876543210',
            'is_quick_created' => false,
            'name' => 'Quick Customer Registered',
        ]);
    }
}
