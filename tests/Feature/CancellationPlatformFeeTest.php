<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CancellationPlatformFeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $turfAdmin;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'payment_gateway_percentage' => 2.00,
            'cancellation_fee_percentage' => 5.00,
        ]);

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $this->turfAdmin = User::factory()->create();
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'City Arena',
            'address' => 'Marine Lines, Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf 1',
            'type' => 'Football',
            'is_cancellation_active' => true,
            'cancellation_hours' => 12,
            'cancellation_fee' => 0.00, // Turf owner allows 0 fee
        ]);
    }

    public function test_saas_admin_can_update_cancellation_fee_percentage(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.settings.application')
            ->set('cancellation_fee_percentage', 4.50)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals(4.50, SaasSetting::first()->cancellation_fee_percentage);
    }

    public function test_cancellation_deducts_platform_fee_when_turf_fee_is_zero(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'booking_type' => 'Single',
            'date_of_booking' => now()->toDateString(),
            'status' => 'Confirmed',
            'total_amount' => 1000.00,
        ]);

        $date = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'amount' => 1000.00,
            'payment_status' => 'Paid',
            'status' => 'Confirmed',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $date->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        // Customer cancels booking
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$date->id],
            ]);

        $response->assertStatus(200);

        // 5% of 1000 = 50.00 platform fee deducted, 950.00 refunded
        $freshBooking = $booking->fresh();
        $this->assertEquals('Cancelled', $freshBooking->status);
        $this->assertEquals(50.00, (float)$freshBooking->cancellation_fee_applied);
        $this->assertEquals(950.00, (float)$freshBooking->refund_amount);

        $freshDate = $date->fresh();
        $this->assertEquals('Cancelled', $freshDate->status);
        $this->assertEquals(50.00, (float)$freshDate->cancellation_fee_applied);
        $this->assertEquals(950.00, (float)$freshDate->refund_amount);
    }

    public function test_cancellation_combines_platform_fee_and_turf_owner_fee(): void
    {
        // Set turf cancellation fee to 100
        $this->turf->update(['cancellation_fee' => 100.00]);

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'booking_type' => 'Single',
            'date_of_booking' => now()->toDateString(),
            'status' => 'Confirmed',
            'total_amount' => 1000.00,
        ]);

        $date = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'amount' => 1000.00,
            'payment_status' => 'Paid',
            'status' => 'Confirmed',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $date->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        // Customer cancels booking
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$date->id],
            ]);

        $response->assertStatus(200);

        // 5% platform fee (50.00) + 100.00 turf fee = 150.00 fee applied, 850.00 refunded
        $freshBooking = $booking->fresh();
        $this->assertEquals(150.00, (float)$freshBooking->cancellation_fee_applied);
        $this->assertEquals(850.00, (float)$freshBooking->refund_amount);
    }
}
