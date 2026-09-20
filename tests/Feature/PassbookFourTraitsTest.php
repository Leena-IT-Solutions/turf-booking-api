<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\User;
use App\Services\WalletService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PassbookFourTraitsTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected Turf $turf;
    protected Slot $slot;
    protected Booking $booking;
    protected BookingDate $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 10.00,
            'platform_fee' => 5.00,
            'platform_fee_type' => 'fixed',
            'platform_fee_gst_percentage' => 0.00,
            'min_slots_booking' => 1,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Sports Hub Location',
            'address' => '456 Bandra, Mumbai',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Premier Arena',
            'type' => 'Football',
            'is_active' => true,
            'is_online_payment_active' => true,
            'is_pay_at_location_active' => true,
        ]);

        $category = SlotCategory::create(['name' => 'General']);

        $this->slot = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '7-8 PM Evening',
            'from_time' => '19:00:00',
            'to_time' => '20:00:00',
            'duration' => 60,
        ]);

        $customer = User::factory()->create();

        $this->booking = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $customer->id,
            'date_of_booking' => now()->format('Y-m-d'),
            'total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
            'platform_fee' => 5.00,
            'platform_fee_gst' => 0.00,
            'commission_amount' => 100.00,
            'commission_gst_amount' => 0.00,
            'payment_status' => 'Paid',
            'status' => 'Confirmed',
        ]);

        $this->bookingDate = BookingDate::create([
            'booking_id' => $this->booking->id,
            'booking_date' => now()->format('Y-m-d'),
            'amount' => 1000.00,
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
            'status' => 'Confirmed',
        ]);
    }

    public function test_online_booking_records_all_four_passbook_traits()
    {
        $walletService = new WalletService();

        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'booking_date_id' => $this->bookingDate->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'commission_percentage' => 10.00,
            'commission_amount' => 100.00,
            'commission_gst_amount' => 0.00,
            'cash_held_amount' => 1000.00,
            'turf_payout_amount' => 871.40,
            'gateway_charge_amount' => 20.00,
            'gateway_tax_amount' => 3.60,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        // Settle payment with traits for online booking
        $walletService->settlePaymentWithTraits($this->turfAdmin, $payment, true);

        // Fetch wallet transactions for this turf admin
        $transactions = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->orderBy('id', 'asc')
            ->get();

        // Must record all 4 traits:
        // 1. Payment Credit (Gross: +1000.00)
        // 2. Platform Fee (-5.00)
        // 3. Commission (-100.00)
        // 4. Gateway Charges (-23.60)
        $this->assertCount(4, $transactions);

        // Trait 1: Payment Credit
        $t1 = $transactions[0];
        $this->assertEquals('payment_credit', $t1->type);
        $this->assertEquals(1000.00, (float)$t1->amount);
        $this->assertEquals(1000.00, (float)$t1->balance_after);
        $this->assertStringContainsString('Online Payment Received', $t1->description);

        // Trait 2: Platform Fee
        $t2 = $transactions[1];
        $this->assertEquals('platform_fee_debit', $t2->type);
        $this->assertEquals(-5.00, (float)$t2->amount);
        $this->assertEquals(995.00, (float)$t2->balance_after);
        $this->assertStringContainsString('Platform Fee', $t2->description);

        // Trait 3: Commission
        $t3 = $transactions[2];
        $this->assertEquals('commission_debit', $t3->type);
        $this->assertEquals(-100.00, (float)$t3->amount);
        $this->assertEquals(895.00, (float)$t3->balance_after);
        $this->assertStringContainsString('Platform Commission', $t3->description);

        // Trait 4: PG Charges
        $t4 = $transactions[3];
        $this->assertEquals('gateway_charge_debit', $t4->type);
        $this->assertEquals(-23.60, (float)$t4->amount);
        $this->assertEquals(871.40, (float)$t4->balance_after);
        $this->assertStringContainsString('Payment Gateway Charges', $t4->description);

        // Net wallet balance should equal final balance_after (871.40)
        $this->turfAdmin->refresh();
        $this->assertEquals(871.40, (float)$this->turfAdmin->commission_wallet_balance);
    }

    public function test_offline_pay_at_venue_records_platform_fee_commission_and_record_traits()
    {
        $walletService = new WalletService();

        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'booking_date_id' => $this->bookingDate->id,
            'payment_method' => 'offline',
            'amount' => 1000.00,
            'commission_percentage' => 10.00,
            'commission_amount' => 100.00,
            'commission_gst_amount' => 0.00,
            'cash_held_amount' => 0.00,
            'turf_payout_amount' => -105.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        // Settle payment with traits for offline booking
        $walletService->settlePaymentWithTraits($this->turfAdmin, $payment, false);

        $transactions = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(3, $transactions);

        // Trait 1: Platform Fee debit (-5.00)
        $t1 = $transactions[0];
        $this->assertEquals('platform_fee_debit', $t1->type);
        $this->assertEquals(-5.00, (float)$t1->amount);
        $this->assertEquals(-5.00, (float)$t1->balance_after);

        // Trait 2: Commission debit (-100.00)
        $t2 = $transactions[1];
        $this->assertEquals('commission_debit', $t2->type);
        $this->assertEquals(-100.00, (float)$t2->amount);
        $this->assertEquals(-105.00, (float)$t2->balance_after);

        // Trait 3: Offline booking record (0.00)
        $t3 = $transactions[2];
        $this->assertEquals('offline_booking_record', $t3->type);
        $this->assertEquals(0.00, (float)$t3->amount);
        $this->assertEquals(-105.00, (float)$t3->balance_after);
        $this->assertStringContainsString('Pay at Venue', $t3->description);

        $this->turfAdmin->refresh();
        $this->assertEquals(-105.00, (float)$this->turfAdmin->commission_wallet_balance);
    }

    public function test_passbook_livewire_component_renders_four_traits()
    {
        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'booking_date_id' => $this->bookingDate->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'status' => 'Success',
        ]);

        CommissionWalletTransaction::create([
            'user_id' => $this->turfAdmin->id,
            'type' => 'payment_credit',
            'amount' => 1000.00,
            'balance_after' => 1000.00,
            'description' => "Booking #{$this->booking->id} Online Payment Received",
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        CommissionWalletTransaction::create([
            'user_id' => $this->turfAdmin->id,
            'type' => 'platform_fee_debit',
            'amount' => -5.00,
            'balance_after' => 995.00,
            'description' => "Booking #{$this->booking->id} Platform Fee",
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        CommissionWalletTransaction::create([
            'user_id' => $this->turfAdmin->id,
            'type' => 'commission_debit',
            'amount' => -100.00,
            'balance_after' => 895.00,
            'description' => "Booking #{$this->booking->id} Platform Commission",
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        CommissionWalletTransaction::create([
            'user_id' => $this->turfAdmin->id,
            'type' => 'gateway_charge_debit',
            'amount' => -23.60,
            'balance_after' => 871.40,
            'description' => "Booking #{$this->booking->id} Payment Gateway Charges",
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
        ]);

        // Test Livewire component
        $this->actingAs($this->turfAdmin);

        \Livewire\Volt\Volt::test('turf.business-manager')
            ->assertSee('Payment Received')
            ->assertSee('Platform Fee')
            ->assertSee('Commission')
            ->assertSee('PG Charges')
            ->assertSee('+₹1,000.00')
            ->assertSee('-₹5.00')
            ->assertSee('-₹100.00')
            ->assertSee('-₹23.60');
    }
}
