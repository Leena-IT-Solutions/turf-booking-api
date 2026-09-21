<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BusinessPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        \App\Models\SaasSetting::create([
            'commission_percentage' => 7.00,
        ]);

        $this->turfAdmin = User::factory()->create([

            'commission_wallet_balance' => 1500.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_turf_admin_can_access_business_page()
    {
        $response = $this->actingAs($this->turfAdmin)->get('/turf/business');
        $response->assertStatus(200);
    }

    public function test_business_page_renders_wallet_badges_and_forms()
    {
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.business-manager')
            ->assertSee('AVAILABLE FOR WITHDRAWAL')
            ->assertSee('Wallet Statement & Commission Ledger', false);
    }

    public function test_wallet_statement_loads_more_rows_on_scroll_instead_of_paginating()
    {
        $this->actingAs($this->turfAdmin);

        foreach (range(1, 15) as $i) {
            \App\Models\CommissionWalletTransaction::create([
                'user_id' => $this->turfAdmin->id,
                'type' => 'commission_debit',
                'amount' => -1.00,
                'balance_after' => 0.00,
                'description' => "Marker TX {$i}",
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        $component = Volt::test('turf.business-manager');

        // Initially only the 10 most recent (TX 15 down to TX 6) are shown, with an
        // auto-load-more trigger instead of numbered page links.
        $component->assertSee('Marker TX 15', false)
            ->assertSee('Marker TX 6', false)
            ->assertDontSee('Marker TX 5', false)
            ->assertSee('Loading more', false)
            ->assertDontSee('End of statement', false);

        $component->call('loadMoreTx');

        // After loading more, all 15 rows are visible and the end-of-list marker shows instead.
        $component->assertSee('Marker TX 1', false)
            ->assertSee('End of statement', false);
    }

    public function test_pending_clearance_panel_renders_when_uncleared_online_payments_exist(): void
    {
        $this->actingAs($this->turfAdmin);

        // 1. Without any pending payments, panel shouldn't render
        Volt::test('turf.business-manager')
            ->assertDontSee('Pending Clearance — not yet in the statement below', false);

        // Create turf belonging to turfAdmin
        $location = \App\Models\Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'City Arena',
            'address' => 'Mumbai',
        ]);
        $turf = \App\Models\Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf 1',
            'type' => 'Football',
        ]);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $booking = \App\Models\Booking::create([
            'booking_number' => 'TB-PENDING-001',
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 500.00,
        ]);
        $bookingDate = \App\Models\BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'Confirmed',
            'amount' => 500.00,
            'paid_amount' => 500.00,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
        ]);

        // Create uncleared online payment
        $payment = \App\Models\Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bookingDate->id,
            'payment_method' => 'App',
            'amount' => 500.00,
            'status' => 'Success',
            'turf_payout_amount' => 450.00,
            'wallet_cleared_at' => null,
        ]);

        // 2. Now the panel should render with booking and payout details
        Volt::test('turf.business-manager')
            ->assertSee('Pending Clearance — not yet in the statement below', false)
            ->assertSee("Booking #{$booking->id}", false)
            ->assertSee('₹450.00 online payment pending clearance', false)
            ->assertSee(\Carbon\Carbon::parse($bookingDate->booking_date)->format('d M Y'), false);

        // 3. Mark payment as cleared -> panel should disappear
        $payment->update(['wallet_cleared_at' => now()]);

        Volt::test('turf.business-manager')
            ->assertDontSee('Pending Clearance — not yet in the statement below', false);
    }
}
