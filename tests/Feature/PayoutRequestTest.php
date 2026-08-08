<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'payout_hours' => 24,
            'payout_charges' => 40.00,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 1000.00,
            'payout_method' => 'bank',
            'bank_account_name' => 'Sandeep Rathod',
            'bank_account_number' => '1234567890',
            'bank_ifsc' => 'SBIN0001234',
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_payout_failed_pre_api_error_restores_wallet_balance()
    {
        // Fake Razorpay API to return 500 server error on contact creation
        Http::fake([
            'https://api.razorpay.com/v1/contacts' => Http::response(['error' => ['description' => 'Razorpay API Down']], 500),
        ]);

        SaasSetting::first()->update([
            'razorpay_key' => 'rzp_test_123',
            'razorpay_secret' => 'secret_123',
            'razorpayx_account_number' => '23344556677',
        ]);

        $payoutService = new \App\Services\PayoutService();
        $payout = $payoutService->requestPayout($this->turfAdmin, 500.00, 'manual');

        $payout->refresh();
        $this->assertEquals('failed', $payout->status);
        $this->assertNotNull($payout->failure_reason);

        // Wallet balance must be completely restored back to 1000.00
        $this->turfAdmin->refresh();
        $this->assertEquals(1000.00, (float)$this->turfAdmin->commission_wallet_balance);
    }
}
