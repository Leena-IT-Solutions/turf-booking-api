<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BankingPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 500.00,
            'payout_method' => 'bank',
            'bank_account_name' => 'Sandeep Rathod',
            'bank_account_number' => '9876543210123',
            'bank_ifsc' => 'SBIN0001234',
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_turf_admin_can_access_banking_page(): void
    {
        $response = $this->actingAs($this->turfAdmin)->get(route('turf.banking'));

        $response->assertStatus(200);
        $response->assertSee('Request Payout');
        $response->assertSee('Payout Receiving Details');
        $response->assertSee('Automatic Payout Schedule');
    }

    public function test_turf_admin_can_save_bank_details(): void
    {
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.banking-manager')
            ->set('payoutMethod', 'bank')
            ->set('bankAccountName', 'Sandeep Rathod')
            ->set('bankAccountNumber', '112233445566')
            ->set('bankIfsc', 'HDFC0001234')
            ->call('saveKycDetails')
            ->assertHasNoErrors()
            ->assertSee('Payout receiving details saved successfully!');

        $this->assertDatabaseHas('users', [
            'id' => $this->turfAdmin->id,
            'payout_method' => 'bank',
            'bank_account_number' => '112233445566',
            'bank_ifsc' => 'HDFC0001234',
        ]);
    }

    public function test_turf_admin_can_save_payout_schedule(): void
    {
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.banking-manager')
            ->set('payoutSchedule', 'weekly')
            ->set('payoutScheduleDay', 2)
            ->call('saveSchedulePreference')
            ->assertHasNoErrors()
            ->assertSee('Payout schedule preference updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $this->turfAdmin->id,
            'payout_schedule' => 'weekly',
            'payout_schedule_day' => 2,
        ]);
    }
}
