<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AutomaticPayoutScheduleTest extends TestCase
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

            'commission_wallet_balance' => 800.00,
            'payout_schedule' => 'daily',
            'payout_method' => 'bank',
            'bank_account_name' => 'Sandeep Rathod',
            'bank_account_number' => '1234567890',
            'bank_ifsc' => 'SBIN0001234',
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_save_schedule_preference_via_volt_component()
    {
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.business-manager')
            ->set('payoutSchedule', 'weekly')
            ->set('payoutScheduleDay', 3) // Wednesday
            ->call('saveSchedulePreference')
            ->assertHasNoErrors();


        $this->turfAdmin->refresh();
        $this->assertEquals('weekly', $this->turfAdmin->payout_schedule);
        $this->assertEquals(3, $this->turfAdmin->payout_schedule_day);
    }

    public function test_automatic_payout_command_triggers_payout_for_daily_scheduled_users()
    {
        Artisan::call('payouts:run-automatic');

        $this->assertDatabaseHas('turf_payouts', [
            'user_id' => $this->turfAdmin->id,
            'triggered_by' => 'scheduled',
            'requested_amount' => 800.00,
        ]);
    }
}
