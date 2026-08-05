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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WalletClearanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'payment_gateway_percentage' => 2.00,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Future Location',
            'address' => '456 FC Road, Pune',
            'city' => 'Pune',
        ]);


        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Future Turf',
            'type' => 'Football',
        ]);

    }

    public function test_future_online_payment_remains_pending_until_matured_and_cleared_by_artisan_command()
    {
        $futureDate = now()->addDays(5)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->turfAdmin->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $futureDate,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);


        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $futureDate,
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'commission_percentage' => 7.00,
            'commission_amount' => 70.00,
            'cash_held_amount' => 1000.00,
            'turf_payout_amount' => 930.00,
            'status' => 'Success',
            'wallet_cleared_at' => null, // Pending clearance because booking date is in future
        ]);

        // Before clearance command
        $this->turfAdmin->refresh();
        $this->assertEquals(0.00, (float)$this->turfAdmin->commission_wallet_balance);

        // Run clearance command today (booking date is in future, so 0 cleared)
        Artisan::call('wallet:clear-matured-entries');
        $payment->refresh();
        $this->assertNull($payment->wallet_cleared_at);

        // Fast-forward time past booking date
        $bDate->update(['booking_date' => now()->subDay()->format('Y-m-d')]);

        Artisan::call('wallet:clear-matured-entries');

        $payment->refresh();
        $this->assertNotNull($payment->wallet_cleared_at);

        $this->turfAdmin->refresh();
        $this->assertEquals(930.00, (float)$this->turfAdmin->commission_wallet_balance);
    }
}
