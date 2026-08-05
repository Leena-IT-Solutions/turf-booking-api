<?php

namespace Tests\Feature;

use App\Models\CommissionSettlement;
use App\Models\SaasSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CommissionDueSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'razorpay_secret' => 'rzp_sec_555',
        ]);


        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => -1500.00,
            'commission_due_since' => now()->subDays(3),
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_settlement_rejected_on_invalid_hmac_signature()
    {
        $settlement = CommissionSettlement::create([
            'user_id' => $this->turfAdmin->id,
            'amount' => 1500.00,
            'razorpay_order_id' => 'order_due_123',
            'status' => 'created',
        ]);

        $this->actingAs($this->turfAdmin);

        Volt::test('turf.business-manager')
            ->call('verifySettlementPayment', [
                'settlement_id' => $settlement->id,
                'razorpay_payment_id' => 'pay_fake_888',
                'razorpay_signature' => 'invalid_fake_signature',
            ])
            ->assertHasNoErrors();

        $settlement->refresh();
        $this->assertEquals('created', $settlement->status);

        $this->turfAdmin->refresh();
        $this->assertEquals(-1500.00, (float)$this->turfAdmin->commission_wallet_balance);
    }

    public function test_settlement_succeeds_on_valid_hmac_signature()
    {
        $orderId = 'order_due_777';
        $paymentId = 'pay_real_999';
        $signature = hash_hmac('sha256', $orderId . '|' . $paymentId, 'rzp_sec_555');

        $settlement = CommissionSettlement::create([
            'user_id' => $this->turfAdmin->id,
            'amount' => 1500.00,
            'razorpay_order_id' => $orderId,
            'status' => 'created',
        ]);

        $this->actingAs($this->turfAdmin);

        Volt::test('turf.business-manager')
            ->call('verifySettlementPayment', [
                'settlement_id' => $settlement->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);


        $settlement->refresh();
        $this->assertEquals('paid', $settlement->status);

        // Wallet balance updated: -1500 + 1500 = 0.00
        $this->turfAdmin->refresh();
        $this->assertEquals(0.00, (float)$this->turfAdmin->commission_wallet_balance);
        $this->assertNull($this->turfAdmin->commission_due_since);
    }
}
