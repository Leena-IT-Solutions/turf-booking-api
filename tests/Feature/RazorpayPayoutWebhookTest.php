<?php

namespace Tests\Feature;

use App\Models\PayoutWebhook;
use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RazorpayPayoutWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'razorpayx_webhook_secret' => 'whsecret_12345',
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
    }

    public function test_missing_signature_rejected_when_secret_configured()
    {
        $payload = [
            'event' => 'payout.processed',
            'event_id' => 'evt_test_123',
        ];

        $response = $this->postJson('/api/razorpay/payout-webhook', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Missing X-Razorpay-Signature header');
    }

    public function test_valid_signature_payout_processed_completes_payout()
    {
        $payout = TurfPayout::create([
            'user_id' => $this->turfAdmin->id,
            'requested_amount' => 500.00,
            'charge_applied' => 0.00,
            'net_amount' => 500.00,
            'status' => 'processing',
            'triggered_by' => 'manual',
            'razorpay_payout_id' => 'pout_rzp_999',
        ]);

        $payloadData = [
            'event' => 'payout.processed',
            'event_id' => 'evt_processed_101',
            'payload' => [
                'payout' => [
                    'entity' => [
                        'id' => 'pout_rzp_999',
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
                'HTTP_X-Razorpay-Event-Id' => 'evt_processed_101',
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawContent
        );

        $response->assertStatus(200);

        $payout->refresh();
        $this->assertEquals('completed', $payout->status);
    }
}
