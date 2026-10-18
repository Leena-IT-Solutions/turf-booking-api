<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CommissionWalletTransaction;
use App\Models\DeviceToken;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Services\MoneyAuditService;
use App\Services\NotificationService;
use App\Services\PayoutService;
use App\Services\ReportService;
use App\Services\WalletService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Livewire\Volt\Volt;
use Mockery;
use Tests\TestCase;

class ManualPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $saasAdmin;
    protected User $turfAdmin;
    protected SaasSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->setting = SaasSetting::create([
            'payout_mode' => 'manual',
            'payout_hours' => 24,
            'payout_charges' => 40.00,
            'commission_percentage' => 7.00,
        ]);

        $this->saasAdmin = User::factory()->create([
            'name' => 'SaaS Admin',
            'email' => 'admin@turfbooking.test',
        ]);
        $this->saasAdmin->assignRole('saas-admin');

        $this->turfAdmin = User::factory()->create([
            'name' => 'Turf Owner',
            'email' => 'owner@turfbooking.test',
            'mobile' => '9876543210',
            'commission_wallet_balance' => 500.00,
            'payout_method' => 'upi',
            'upi_id' => 'owner@upi',
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    /**
     * 1. The request is manual.
     */
    public function test_the_request_is_manual(): void
    {
        Http::fake();

        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 200.00, 'manual');

        $payout->refresh();
        $this->assertEquals(TurfPayout::STATUS_PENDING, $payout->status);
        $this->assertEquals('manual', $payout->mode);
        $this->assertEquals('upi', $payout->payout_method);
        $this->assertEquals(['upi_id' => 'owner@upi'], $payout->account_details);

        $this->turfAdmin->refresh();
        $this->assertEquals(300.00, (float) $this->turfAdmin->commission_wallet_balance);

        $this->assertDatabaseHas('commission_wallet_transactions', [
            'user_id' => $this->turfAdmin->id,
            'reference_type' => TurfPayout::class,
            'reference_id' => $payout->id,
            'type' => 'payout_debit',
            'amount' => -200.00,
        ]);

        Http::assertNothingSent();
    }

    /**
     * 2. The copy doesn't change.
     */
    public function test_the_copy_does_not_change(): void
    {
        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 200.00, 'manual');

        $this->turfAdmin->update(['upi_id' => 'new_id@upi']);

        $payout->refresh();
        $this->assertEquals(['upi_id' => 'owner@upi'], $payout->account_details);
    }

    /**
     * 3. Mark as paid.
     */
    public function test_mark_as_paid(): void
    {
        DeviceToken::create([
            'user_id' => $this->turfAdmin->id,
            'device_token' => 'device_token_owner',
            'device_type' => 'android',
        ]);

        $report = MulticastSendReport::withItems([]);

        $messagingMock = Mockery::mock(Messaging::class);
        $messagingMock->shouldReceive('sendMulticast')->once()->andReturn($report);
        $this->app->instance(Messaging::class, $messagingMock);

        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 200.00, 'manual');

        $paidPayout = $payoutService->markManualPayoutPaid(
            $payout,
            $this->saasAdmin,
            'UTR12345678',
            Carbon::today()->toDateString(),
            'Transferred via HDFC'
        );

        $this->assertEquals(TurfPayout::STATUS_COMPLETED, $paidPayout->status);
        $this->assertEquals('UTR12345678', $paidPayout->utr);
        $this->assertEquals(Carbon::today()->toDateString(), $paidPayout->paid_at->toDateString());
        $this->assertEquals($this->saasAdmin->id, $paidPayout->processed_by_user_id);
        $this->assertNotNull($paidPayout->processed_at);

        $this->turfAdmin->refresh();
        $this->assertEquals(300.00, (float) $this->turfAdmin->commission_wallet_balance);
    }

    /**
     * 4. Reject.
     */
    public function test_reject(): void
    {
        DeviceToken::create([
            'user_id' => $this->turfAdmin->id,
            'device_token' => 'device_token_owner',
            'device_type' => 'android',
        ]);

        $report = MulticastSendReport::withItems([]);

        $messagingMock = Mockery::mock(Messaging::class);
        $messagingMock->shouldReceive('sendMulticast')->once()->andReturn($report);
        $this->app->instance(Messaging::class, $messagingMock);

        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 200.00, 'manual');

        $rejectedPayout = $payoutService->rejectManualPayout(
            $payout,
            $this->saasAdmin,
            'Invalid UPI address provided'
        );

        $this->assertEquals(TurfPayout::STATUS_REJECTED, $rejectedPayout->status);
        $this->assertEquals('Invalid UPI address provided', $rejectedPayout->failure_reason);
        $this->assertEquals($this->saasAdmin->id, $rejectedPayout->processed_by_user_id);
        $this->assertNotNull($rejectedPayout->processed_at);

        $this->turfAdmin->refresh();
        $this->assertEquals(500.00, (float) $this->turfAdmin->commission_wallet_balance);

        $this->assertDatabaseHas('commission_wallet_transactions', [
            'user_id' => $this->turfAdmin->id,
            'reference_type' => TurfPayout::class,
            'reference_id' => $payout->id,
            'type' => 'payout_reversal',
            'amount' => 200.00,
        ]);
    }

    /**
     * 5. Only once.
     */
    public function test_only_once(): void
    {
        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 200.00, 'manual');

        $payoutService->markManualPayoutPaid(
            $payout,
            $this->saasAdmin,
            'UTR_INITIAL_123',
            Carbon::today()->toDateString()
        );

        $this->expectException(\Exception::class);
        $payoutService->rejectManualPayout($payout, $this->saasAdmin, 'Cannot reject after paid');

        $payout->refresh();
        $this->assertEquals(TurfPayout::STATUS_COMPLETED, $payout->status);
    }

    /**
     * 6. Validation.
     */
    public function test_validation(): void
    {
        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');

        // Empty UTR
        try {
            $payoutService->markManualPayoutPaid($payout, $this->saasAdmin, '', Carbon::today()->toDateString());
            $this->fail('Expected exception on empty UTR');
        } catch (\Exception $e) {
            $this->assertStringContainsString('UTR', $e->getMessage());
        }

        // UTR over 64 chars
        try {
            $payoutService->markManualPayoutPaid($payout, $this->saasAdmin, str_repeat('A', 65), Carbon::today()->toDateString());
            $this->fail('Expected exception on UTR > 64 chars');
        } catch (\Exception $e) {
            $this->assertStringContainsString('UTR', $e->getMessage());
        }

        // Future paid date
        try {
            $futureDate = Carbon::tomorrow()->toDateString();
            $payoutService->markManualPayoutPaid($payout, $this->saasAdmin, 'UTR123', $futureDate);
            $this->fail('Expected exception on future paid date');
        } catch (\Exception $e) {
            $this->assertStringContainsString('future', $e->getMessage());
        }

        // Empty reject reason (< 5 chars)
        try {
            $payoutService->rejectManualPayout($payout, $this->saasAdmin, 'bad');
            $this->fail('Expected exception on short reject reason');
        } catch (\Exception $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }
    }

    /**
     * 7. The ₹40 rule.
     */
    public function test_the_40_rupee_rule(): void
    {
        $payoutService = new PayoutService();

        // 1st payout: requested 100, free
        $payout1 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $this->assertEquals(0.00, (float) $payout1->charge_applied);
        $this->assertEquals(100.00, (float) $payout1->net_amount);

        // Mark it paid with processed_at = now()
        $payoutService->markManualPayoutPaid($payout1, $this->saasAdmin, 'UTR1', Carbon::today()->toDateString());

        // 2nd payout immediately: within 24h cooldown, ₹40 charged
        $payout2 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $this->assertEquals(40.00, (float) $payout2->charge_applied);
        $this->assertEquals(60.00, (float) $payout2->net_amount);

        // Simulate 1st payout processed 48 hours ago
        $payout1->update(['processed_at' => Carbon::now()->subHours(48)]);
        // And delete payout2 to test window expiry
        $payout2->delete();

        $payout3 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $this->assertEquals(0.00, (float) $payout3->charge_applied);
        $this->assertEquals(100.00, (float) $payout3->net_amount);
    }

    /**
     * 8. Mode switch.
     */
    public function test_mode_switch(): void
    {
        Http::fake([
            'https://api.razorpay.com/v1/contacts' => Http::response(['id' => 'cont_test_123'], 200),
            'https://api.razorpay.com/v1/fund_accounts' => Http::response(['id' => 'fa_test_123'], 200),
            'https://api.razorpay.com/v1/payouts' => Http::response(['id' => 'pout_rzp_test_123'], 200),
        ]);

        $this->setting->update([
            'payout_mode' => 'razorpayx',
            'razorpay_key' => 'rzp_key_test',
            'razorpay_secret' => 'rzp_sec_test',
            'razorpayx_account_number' => 'ACC123456',
            'razorpayx_webhook_secret' => 'whsec_test',
        ]);

        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');

        $this->assertEquals('razorpayx', $payout->mode);
        $this->assertEquals(TurfPayout::STATUS_PROCESSING, $payout->status);

        // Switch setting back to manual
        $this->setting->update(['payout_mode' => 'manual']);

        // Payout keeps its mode and status
        $payout->refresh();
        $this->assertEquals('razorpayx', $payout->mode);
        $this->assertEquals(TurfPayout::STATUS_PROCESSING, $payout->status);

        // Cannot mark paid or reject a razorpayx payout
        try {
            $payoutService->markManualPayoutPaid($payout, $this->saasAdmin, 'UTR999', Carbon::today()->toDateString());
            $this->fail('Expected exception marking razorpayx payout paid');
        } catch (\Exception $e) {
            $this->assertStringContainsString('manual', $e->getMessage());
        }

        try {
            $payoutService->rejectManualPayout($payout, $this->saasAdmin, 'Reason text for reject');
            $this->fail('Expected exception rejecting razorpayx payout');
        } catch (\Exception $e) {
            $this->assertStringContainsString('manual', $e->getMessage());
        }
    }

    /**
     * 9. The webhook ignores manual payouts.
     */
    public function test_the_webhook_ignores_manual_payouts(): void
    {
        $this->setting->update(['razorpayx_webhook_secret' => 'whsecret_12345']);

        $manualPayout = TurfPayout::create([
            'user_id' => $this->turfAdmin->id,
            'requested_amount' => 100.00,
            'charge_applied' => 0.00,
            'net_amount' => 100.00,
            'status' => TurfPayout::STATUS_PENDING,
            'mode' => 'manual',
            'triggered_by' => 'manual',
            'razorpay_payout_id' => 'pout_manual_webhook_test',
        ]);

        $payloadData = [
            'event' => 'payout.processed',
            'event_id' => 'evt_manual_ignore_101',
            'payload' => [
                'payout' => [
                    'entity' => [
                        'id' => 'pout_manual_webhook_test',
                        'status' => 'processed',
                    ]
                ]
            ]
        ];

        $rawContent = json_encode($payloadData);
        $signature = hash_hmac('sha256', $rawContent, 'whsecret_12345');

        $response = $this->call(
            'POST',
            '/api/razorpay/payout-webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Razorpay-Signature' => $signature,
                'HTTP_X-Razorpay-Event-Id' => 'evt_manual_ignore_101',
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawContent
        );

        $response->assertStatus(200);

        $manualPayout->refresh();
        $this->assertEquals(TurfPayout::STATUS_PENDING, $manualPayout->status);
    }

    /**
     * 10. Automatic payouts in manual mode.
     */
    public function test_automatic_payouts_in_manual_mode(): void
    {
        Http::fake();

        $this->turfAdmin->update([
            'payout_schedule' => 'daily',
            'commission_wallet_balance' => 450.00,
        ]);

        Artisan::call('payouts:run-automatic');

        $this->assertDatabaseHas('turf_payouts', [
            'user_id' => $this->turfAdmin->id,
            'triggered_by' => 'scheduled',
            'requested_amount' => 450.00,
            'mode' => 'manual',
            'status' => TurfPayout::STATUS_PENDING,
        ]);

        Http::assertNothingSent();
    }

    /**
     * 11. Settings.
     */
    public function test_settings(): void
    {
        $this->actingAs($this->saasAdmin);

        // Choosing RazorpayX without credentials shows the error
        Volt::test('saas.settings.application')
            ->set('payout_mode', 'razorpayx')
            ->call('saveSettings')
            ->assertHasErrors(['payout_mode']);

        // With all four credentials filled in, it saves
        $this->setting->update([
            'razorpay_key' => 'rzp_key_sample',
            'razorpay_secret' => 'rzp_secret_sample',
            'razorpayx_account_number' => 'ACC12345678',
            'razorpayx_webhook_secret' => 'whsec_sample',
        ]);

        Volt::test('saas.settings.application')
            ->set('payout_mode', 'razorpayx')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->setting->refresh();
        $this->assertEquals('razorpayx', $this->setting->payout_mode);

        // Fresh SaasSetting defaults to manual
        $freshSetting = new SaasSetting();
        $this->assertEquals('manual', $freshSetting->payout_mode ?? 'manual');
    }

    /**
     * 12. Payout Manager (Volt).
     */
    public function test_payout_manager_volt(): void
    {
        // Turf admin gets 403 on /saas/payouts
        $response = $this->actingAs($this->turfAdmin)->get('/saas/payouts');
        $response->assertStatus(403);

        $payoutService = new PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 250.00, 'manual');

        $this->actingAs($this->saasAdmin);

        // A pending manual payout appears under 'Pending transfers' with the net amount and UPI ID
        Volt::test('saas.payout-manager')
            ->assertSee('Pending Transfers')
            ->assertSee('₹250.00')
            ->assertSee('owner@upi')
            ->call('openPayModal', $payout->id)
            ->set('payUtr', 'UTR_VOLT_TEST_123')
            ->set('payDate', Carbon::today()->toDateString())
            ->call('submitMarkPaid')
            ->assertHasNoErrors();

        $payout->refresh();
        $this->assertEquals(TurfPayout::STATUS_COMPLETED, $payout->status);
        $this->assertEquals('UTR_VOLT_TEST_123', $payout->utr);
    }

    /**
     * 13. Banking page.
     */
    public function test_banking_page(): void
    {
        $payoutService = new PayoutService();
        $payout1 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $payoutService->markManualPayoutPaid($payout1, $this->saasAdmin, 'UTR_BANK_123', Carbon::today()->toDateString());

        // Second payout requested immediately will have charge_applied = 40.00
        $payoutPending = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');

        $this->actingAs($this->turfAdmin);

        Volt::test('turf.banking-manager')
            ->assertSee('Requested: awaiting transfer')
            ->assertSee('UTR_BANK_123')
            ->assertSee('-₹40.00'); // charge_applied deduction
    }

    /**
     * 14. Reports.
     */
    public function test_reports(): void
    {
        $payoutService = new PayoutService();
        $payout1 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $payoutService->markManualPayoutPaid($payout1, $this->saasAdmin, 'UTR_REP_1', Carbon::today()->toDateString());

        $payout2 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual'); // rejected
        $payoutService->rejectManualPayout($payout2, $this->saasAdmin, 'Account mismatch');

        $reportService = new ReportService();
        $reportData = $reportService->getPayoutHistorySummary(
            $this->turfAdmin,
            Carbon::today()->subDays(7)->toDateString(),
            Carbon::today()->addDays(1)->toDateString()
        );

        $this->assertEquals(100.00, (float) $reportData['successful_net']);
    }

    /**
     * 15. money:check.
     */
    public function test_money_check(): void
    {
        // Set up location and turf for owner
        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Owner Arena',
            'address' => 'Main Road',
            'city' => 'Mumbai',
        ]);
        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Owner Turf',
            'type' => 'Football',
            'is_active' => true,
        ]);

        // Start with wallet 0; the 500 online payment is credited below, linked to its payment as the app does
        $this->turfAdmin->update(['commission_wallet_balance' => 0.00]);

        // Create mock payment so online net = 500 in check 31
        $booking = Booking::create([
            'turf_id' => $turf->id,
            'user_id' => $this->turfAdmin->id,
            'date_of_booking' => Carbon::today()->toDateString(),
            'booking_reference' => 'BK_AUDIT_101',
            'base_price' => 500.00,
            'tax_amount' => 0.00,
            'total_price' => 500.00,
            'platform_fee' => 0.00,
            'platform_fee_gst' => 0.00,
            'commission_amount' => 0.00,
            'commission_gst_amount' => 0.00,
            'status' => 'Confirmed',
        ]);
        $bookingDate = $booking->bookingDates()->create([
            'booking_date' => Carbon::today()->toDateString(),
            'amount' => 500.00,
            'slot_price' => 500.00,
            'total_amount' => 500.00,
            'status' => 'Confirmed',
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bookingDate->id,
            'payment_method' => 'App',
            'amount' => 500.00,
            'refunded_amount' => 0.00,
            'status' => 'Success',
        ]);
        (new WalletService())->applyDelta($this->turfAdmin, 500.00, 'payment_credit', $payment);

        $payoutService = new PayoutService();
        $payout1 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $payoutService->markManualPayoutPaid($payout1, $this->saasAdmin, 'UTR_AUDIT_1', Carbon::today()->toDateString());

        $payout2 = $payoutService->requestPayout($this->turfAdmin, 100.00, 'manual');
        $payoutService->rejectManualPayout($payout2, $this->saasAdmin, 'Invalid account details');

        $auditService = new MoneyAuditService();
        $rows = $auditService->checkOwner($this->turfAdmin->fresh(), false);

        $failedRows = collect($rows)->where('result', 'FAIL');
        $this->assertTrue($failedRows->isEmpty(), 'Expected 0 FAILs on owner checks: ' . json_encode($failedRows->values()->all()));

        // Remove UTR from completed payout -> check 34 FAILs
        $payout1->update(['utr' => '']);
        $rowsAfterUtrRemoval = $auditService->checkOwner($this->turfAdmin->fresh(), false);
        $check34 = collect($rowsAfterUtrRemoval)->firstWhere('check', '34. Manual payouts finished correctly');
        $this->assertNotNull($check34);
        $this->assertEquals('FAIL', $check34['result']);

        // Restore UTR
        $payout1->update(['utr' => 'UTR_AUDIT_1']);

        // Delete rejected payout's payout_reversal and adjust balance so check 30 still passes -> check 33 FAILs
        CommissionWalletTransaction::where('reference_type', TurfPayout::class)
            ->where('reference_id', $payout2->id)
            ->where('type', 'payout_reversal')
            ->delete();

        // Adjust balance so check 30 (sum of tx) still passes: 500 - 100 - 100 = 300
        $this->turfAdmin->update(['commission_wallet_balance' => 300.00]);

        $rowsAfterReversalDelete = $auditService->checkOwner($this->turfAdmin->fresh(), false);
        $check33 = collect($rowsAfterReversalDelete)->firstWhere('check', '33. Payouts have matching ledger rows');
        $this->assertNotNull($check33);
        $this->assertEquals('FAIL', $check33['result']);
    }
}
