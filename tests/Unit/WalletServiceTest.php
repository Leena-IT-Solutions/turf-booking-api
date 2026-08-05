<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_delta_updates_balance_and_creates_transaction_log()
    {
        $user = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);

        $walletService = new WalletService();
        $user = $walletService->applyDelta($user, 500.00, 'payment_settlement');

        $this->assertEquals(500.00, (float)$user->commission_wallet_balance);
        $this->assertNull($user->commission_due_since);

        $this->assertDatabaseHas('commission_wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'payment_settlement',
            'amount' => 500.00,
            'balance_after' => 500.00,
        ]);

    }

    public function test_commission_due_since_tracks_negative_balance_drift()
    {
        $user = User::factory()->create([
            'commission_wallet_balance' => 100.00,
            'commission_due_since' => null,
        ]);

        $walletService = new WalletService();

        // Debit pushing balance to negative (-50.00)
        $user = $walletService->applyDelta($user, -150.00, 'payment_settlement');

        $this->assertEquals(-50.00, (float)$user->commission_wallet_balance);
        $this->assertNotNull($user->commission_due_since);

        // Credit clearing balance back to positive (+20.00)
        $user = $walletService->applyDelta($user, 70.00, 'commission_due_settlement');

        $this->assertEquals(20.00, (float)$user->commission_wallet_balance);
        $this->assertNull($user->commission_due_since);
    }

}
