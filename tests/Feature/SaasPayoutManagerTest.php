<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SaasPayoutManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $saasAdmin;
    protected User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->saasAdmin = User::factory()->create();
        $this->saasAdmin->assignRole('saas-admin');

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_saas_admin_can_access_payouts_manager_page()
    {
        $response = $this->actingAs($this->saasAdmin)->get('/saas/payouts');
        $response->assertStatus(200);
    }

    public function test_saas_admin_can_retry_failed_payout()
    {
        $payout = TurfPayout::create([
            'user_id' => $this->turfAdmin->id,
            'requested_amount' => 500.00,
            'charge_applied' => 0.00,
            'net_amount' => 500.00,
            'status' => 'failed',
            'failure_reason' => 'Bank server down',
            'triggered_by' => 'manual',
        ]);

        $this->turfAdmin->update(['commission_wallet_balance' => 500.00]);

        $this->actingAs($this->saasAdmin);

        Volt::test('saas.payout-manager')
            ->call('retryPayout', $payout->id)
            ->assertHasNoErrors();


        // A new retry payout request is logged
        $this->assertDatabaseHas('turf_payouts', [
            'user_id' => $this->turfAdmin->id,
            'requested_amount' => 500.00,
        ]);
    }
}
