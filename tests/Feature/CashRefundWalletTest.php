<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\User;
use App\Services\BookingCancellationService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CashRefundWalletTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected User $customer;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 10.00,
            'cancellation_fee_percentage' => 0.00,
            'is_gst_billing_active' => false,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => -110.00, // e.g. platform fee + commission debited upfront
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Cash Test Arena',
            'address' => '789 Link Road',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Cash Turf',
            'type' => 'Football',
            'is_cancellation_active' => true,
            'cancellation_hours' => 24,
            'cancellation_fee' => 0.00,
        ]);
    }

    public function test_offline_cash_booking_cancellation_does_not_debit_wallet(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $dateStr,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 1010.00,
        ]);

        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $dateStr,
            'amount' => 1010.00,
            'status' => 'Confirmed',
            'paid_amount' => 1010.00,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
        ]);

        // Offline payment recorded at venue (has wallet_cleared_at set by WalletService::settleCredit)
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'Cash',
            'amount' => 1010.00,
            'status' => 'Success',
            'wallet_cleared_at' => now(),
            'turf_payout_amount' => 0.00,
        ]);

        // Cancel via API
        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel", [
            'reason' => 'Customer cancelled offline cash booking',
        ]);

        $response->assertStatus(200);

        // Turf owner wallet should remain exactly -110.00 (NO refund_adjustment debit)
        $this->turfAdmin->refresh();
        $this->assertEquals(-110.00, (float)$this->turfAdmin->commission_wallet_balance);

        $adjustments = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'refund_adjustment')
            ->count();
        $this->assertEquals(0, $adjustments);

        $payment->refresh();
        $this->assertEquals(1010.00, (float)$payment->refunded_amount);
        $this->assertEquals('Cash / Offline Refund', $payment->refund_status);
    }

    public function test_admin_resolution_path_for_offline_channel_does_not_debit_wallet(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $dateStr,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 1000.00,
        ]);

        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $dateStr,
            'amount' => 1000.00,
            'status' => 'Confirmed',
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'offline',
            'amount' => 1000.00,
            'status' => 'Success',
            'wallet_cleared_at' => now(),
            'turf_payout_amount' => 0.00,
        ]);

        $cancellation = BookingCancellation::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'cancelled_by_user_id' => $this->customer->id,
            'canceller_role' => 'customer',
            'cancellation_scope' => 'date',
            'turf_cancellation_fee' => 0.00,
            'platform_fee_retained' => 0.00,
            'saas_cancellation_fee' => 0.00,
            'total_cancellation_fee' => 0.00,
            'refund_amount' => 1000.00,
            'refund_status' => 'Pending Resolution',
        ]);

        $service = app(BookingCancellationService::class);
        $service->resolveRefund($cancellation, 'standard_policy', 'offline', $this->turfAdmin, null, 'Handed cash at venue');

        $this->turfAdmin->refresh();
        $this->assertEquals(-110.00, (float)$this->turfAdmin->commission_wallet_balance);

        $adjustments = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'refund_adjustment')
            ->count();
        $this->assertEquals(0, $adjustments);

        $payment->refresh();
        $this->assertEquals(1000.00, (float)$payment->refunded_amount);
        $this->assertEquals('Cash / Offline Refund', $payment->refund_status);
    }

    public function test_online_booking_cancellation_after_maturity_reverses_credit_correctly(): void
    {
        $this->turfAdmin->update(['commission_wallet_balance' => 930.00]);
        $dateStr = now()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $dateStr,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 1000.00,
        ]);

        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $dateStr,
            'amount' => 1000.00,
            'status' => 'Confirmed',
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'status' => 'Success',
            'turf_payout_amount' => 930.00,
            'wallet_cleared_at' => now(),
        ]);

        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel", [
            'reason' => 'Customer cancellation after maturity',
        ]);

        $response->assertStatus(200);

        $this->turfAdmin->refresh();
        // 930.00 - 1000.00 = -70.00
        $this->assertEquals(-70.00, (float)$this->turfAdmin->commission_wallet_balance);

        $adjustment = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'refund_adjustment')
            ->first();
        $this->assertNotNull($adjustment);
        $this->assertEquals(-1000.00, (float)$adjustment->amount);
    }

    public function test_cumulative_refund_preserves_earlier_partial_refunds(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $dateStr,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 1000.00,
        ]);

        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $dateStr,
            'amount' => 1000.00,
            'status' => 'Confirmed',
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
        ]);

        // Prior partial refund of 200.00 already issued
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'Cash',
            'amount' => 1000.00,
            'refunded_amount' => 200.00,
            'refund_status' => 'Cash / Offline Refund',
            'status' => 'Success',
            'wallet_cleared_at' => now(),
            'turf_payout_amount' => 0.00,
        ]);

        // Full cancellation refund requested (1000.00)
        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel", [
            'reason' => 'Cancel with remaining refund',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        // Refund capped at remaining 800.00, bringing cumulative total to 1000.00
        $this->assertEquals(1000.00, (float)$payment->refunded_amount);
    }

    public function test_online_payment_refunded_in_cash_by_turf_keeps_owner_whole(): void
    {
        // Online money already credited to the owner's wallet; the turf then hands the refund back in cash
        // (e.g. after a failed gateway refund). The owner has paid once, in cash: the wallet must not be
        // debited again, and the refund must not count against the online payment's payout.
        $dateStr = now()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->customer->id, 'turf_id' => $this->turf->id, 'date_of_booking' => now(),
            'booking_type' => 'day', 'status' => 'Confirmed', 'payment_status' => 'Paid', 'total_amount' => 1000.00,
        ]);
        $bDate = BookingDate::create([
            'booking_id' => $booking->id, 'booking_date' => $dateStr, 'amount' => 1000.00, 'status' => 'Confirmed',
            'paid_amount' => 1000.00, 'balance_amount' => 0.00, 'payment_status' => 'Paid',
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id, 'payment_method' => 'App',
            'amount' => 1000.00, 'status' => 'Success', 'wallet_cleared_at' => now(), 'turf_payout_amount' => 890.00,
        ]);
        $this->turfAdmin->update(['commission_wallet_balance' => 890.00]);

        $cancellation = BookingCancellation::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id,
            'cancelled_by_user_id' => $this->customer->id, 'canceller_role' => 'customer', 'cancellation_scope' => 'date',
            'turf_cancellation_fee' => 0.00, 'platform_fee_retained' => 0.00, 'saas_cancellation_fee' => 0.00,
            'total_cancellation_fee' => 0.00, 'refund_amount' => 1000.00, 'refund_status' => 'Pending Resolution',
        ]);

        app(BookingCancellationService::class)
            ->resolveRefund($cancellation, 'standard_policy', 'offline', $this->turfAdmin, null, 'Gateway failed, paid cash');

        $this->turfAdmin->refresh();
        $this->assertEquals(890.00, (float) $this->turfAdmin->commission_wallet_balance);
        $this->assertEquals(0, CommissionWalletTransaction::where('type', 'refund_adjustment')->count());

        $payment->refresh();
        $this->assertEquals('Cash / Offline Refund', $payment->refund_status);
        $this->assertEquals(0.00, (float) $payment->refunded_amount);
        $this->assertEquals(890.00, (float) $payment->turf_payout_amount);
    }

    public function test_cash_booking_cancellation_debits_platform_cancellation_fee_from_wallet(): void
    {
        SaasSetting::first()->update(['cancellation_fee_percentage' => 5.00]);
        $this->turf->update(['cancellation_fee' => 50.00]);

        $dateStr = now()->addDays(2)->format('Y-m-d');
        $booking = Booking::create([
            'user_id' => $this->customer->id, 'turf_id' => $this->turf->id, 'date_of_booking' => now(),
            'booking_type' => 'day', 'status' => 'Confirmed', 'payment_status' => 'Paid', 'total_amount' => 1000.00,
            'platform_fee' => 0.00, 'platform_fee_gst' => 0.00,
        ]);
        $bDate = BookingDate::create([
            'booking_id' => $booking->id, 'booking_date' => $dateStr, 'amount' => 1000.00, 'status' => 'Confirmed',
            'paid_amount' => 1000.00, 'balance_amount' => 0.00, 'payment_status' => 'Paid',
        ]);
        Payment::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id, 'payment_method' => 'Cash',
            'amount' => 1000.00, 'status' => 'Success', 'wallet_cleared_at' => now(), 'turf_payout_amount' => 0.00,
        ]);

        $this->turfAdmin->update(['commission_wallet_balance' => -100.00]);

        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel", [
            'reason' => 'Customer cancelled cash booking',
        ]);
        $response->assertStatus(200);

        // 5% of 1000 = 50.00 SaaS cancellation fee
        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertNotNull($cancellation);
        $this->assertEquals(50.00, (float)$cancellation->saas_cancellation_fee);

        $this->turfAdmin->refresh();
        // -100.00 initial - 50.00 cancellation fee debit = -150.00
        $this->assertEquals(-150.00, (float)$this->turfAdmin->commission_wallet_balance);

        $tx = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'cancellation_fee_debit')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(-50.00, (float)$tx->amount);
        $this->assertEquals('- Cancellation Fee', CommissionWalletTransaction::typeLabel($tx->type));
    }

    public function test_admin_resolution_path_for_cash_booking_debits_platform_cancellation_fee(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');
        $booking = Booking::create([
            'user_id' => $this->customer->id, 'turf_id' => $this->turf->id, 'date_of_booking' => now(),
            'booking_type' => 'day', 'status' => 'Confirmed', 'payment_status' => 'Paid', 'total_amount' => 1000.00,
        ]);
        $bDate = BookingDate::create([
            'booking_id' => $booking->id, 'booking_date' => $dateStr, 'amount' => 1000.00, 'status' => 'Confirmed',
            'paid_amount' => 1000.00, 'balance_amount' => 0.00, 'payment_status' => 'Paid',
        ]);
        Payment::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id, 'payment_method' => 'Cash',
            'amount' => 1000.00, 'status' => 'Success', 'wallet_cleared_at' => now(), 'turf_payout_amount' => 0.00,
        ]);

        $this->turfAdmin->update(['commission_wallet_balance' => 0.00]);

        $cancellation = BookingCancellation::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id,
            'cancelled_by_user_id' => $this->customer->id, 'canceller_role' => 'customer', 'cancellation_scope' => 'date',
            'turf_cancellation_fee' => 75.00, 'platform_fee_retained' => 0.00, 'saas_cancellation_fee' => 25.00,
            'total_cancellation_fee' => 100.00, 'refund_amount' => 900.00, 'refund_status' => 'Pending Resolution',
        ]);

        $service = app(BookingCancellationService::class);
        $service->resolveRefund($cancellation, 'standard_policy', 'offline', $this->turfAdmin, null, 'Cash refund at desk');

        $this->turfAdmin->refresh();
        $this->assertEquals(-25.00, (float)$this->turfAdmin->commission_wallet_balance);

        $tx = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'cancellation_fee_debit')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(-25.00, (float)$tx->amount);
    }

    public function test_online_booking_cancellation_immediately_deducts_platform_fee_from_pending_payout(): void
    {
        SaasSetting::first()->update(['cancellation_fee_percentage' => 5.00]);
        $this->turf->update(['cancellation_fee' => 200.00]);

        $dateStr = now()->addDays(3)->format('Y-m-d');
        $booking = Booking::create([
            'user_id' => $this->customer->id, 'turf_id' => $this->turf->id, 'date_of_booking' => now(),
            'booking_type' => 'day', 'status' => 'Confirmed', 'payment_status' => 'Paid', 'total_amount' => 1000.00,
            'platform_fee' => 0.00, 'platform_fee_gst' => 0.00,
        ]);
        $bDate = BookingDate::create([
            'booking_id' => $booking->id, 'booking_date' => $dateStr, 'amount' => 1000.00, 'status' => 'Confirmed',
            'paid_amount' => 1000.00, 'balance_amount' => 0.00, 'payment_status' => 'Paid',
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id, 'payment_method' => 'App',
            'amount' => 1000.00, 'status' => 'Success', 'wallet_cleared_at' => null, 'turf_payout_amount' => 890.00,
        ]);

        $this->turfAdmin->update(['commission_wallet_balance' => 0.00]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel", [
            'booking_date_ids' => [$bDate->id],
        ]);
        $response->assertStatus(200);

        // Since it's online, NO cancellation_fee_debit is posted to wallet (it is retained from online money)
        $this->assertEquals(0, CommissionWalletTransaction::where('type', 'cancellation_fee_debit')->count());

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertNotNull($cancellation);
        $this->assertEquals(200.00, (float)$cancellation->turf_cancellation_fee);
        $this->assertEquals(50.00, (float)$cancellation->saas_cancellation_fee);
        $this->assertEquals(750.00, (float)$cancellation->refund_amount);

        $payment->refresh();
        // Payment turf_payout_amount immediately drops by refund (750.00) AND platform cancellation fee (50.00):
        // 890.00 - 750.00 - 50.00 = 90.00
        $this->assertEquals(90.00, (float)$payment->turf_payout_amount);
    }

    public function test_fee_is_debited_when_the_refund_uses_up_the_online_money(): void
    {
        // ₹300 online + ₹700 cash. The ₹750 refund takes all ₹300 of online money first, so nothing
        // online is left to cover the platform's ₹50 fee: it must be debited from the wallet.
        Http::fake(['https://api.razorpay.com/*' => Http::response(['id' => 'rfnd_1', 'status' => 'captured', 'amount' => 30000], 200)]);
        SaasSetting::first()->update(['razorpay_key' => 'k', 'razorpay_secret' => 's']);

        $dateStr = now()->addDays(2)->format('Y-m-d');
        $booking = Booking::create([
            'user_id' => $this->customer->id, 'turf_id' => $this->turf->id, 'date_of_booking' => now(),
            'booking_type' => 'day', 'status' => 'Confirmed', 'payment_status' => 'Paid', 'total_amount' => 1000.00,
        ]);
        $bDate = BookingDate::create([
            'booking_id' => $booking->id, 'booking_date' => $dateStr, 'amount' => 1000.00, 'status' => 'Confirmed',
            'paid_amount' => 1000.00, 'balance_amount' => 0.00, 'payment_status' => 'Paid',
        ]);
        $online = Payment::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id, 'payment_method' => 'App',
            'amount' => 300.00, 'status' => 'Success', 'turf_payout_amount' => 300.00,
        ]);
        PaymentGateway::create(['payment_id' => $online->id, 'gateway_name' => 'razorpay', 'gateway_payment_id' => 'pay_on']);
        Payment::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id, 'payment_method' => 'Cash',
            'amount' => 700.00, 'status' => 'Success', 'wallet_cleared_at' => now(), 'turf_payout_amount' => 0.00,
        ]);
        $cancellation = BookingCancellation::create([
            'booking_id' => $booking->id, 'booking_date_id' => $bDate->id,
            'cancelled_by_user_id' => $this->customer->id, 'canceller_role' => 'customer', 'cancellation_scope' => 'date',
            'gross_cancelled_amount' => 1000.00, 'turf_cancellation_fee' => 200.00, 'platform_fee_retained' => 0.00,
            'saas_cancellation_fee' => 50.00, 'total_cancellation_fee' => 250.00, 'refund_amount' => 750.00,
            'refund_status' => 'Pending Resolution',
        ]);

        app(BookingCancellationService::class)->resolveRefund($cancellation, 'standard_policy', 'online_gateway', $this->turfAdmin);

        $this->assertEquals(300.00, (float) $online->fresh()->refunded_amount);
        $this->assertEquals(-50.00, (float) CommissionWalletTransaction::where('type', 'cancellation_fee_debit')->sum('amount'));
        $this->assertEquals(-160.00, (float) $this->turfAdmin->fresh()->commission_wallet_balance);
    }
}
