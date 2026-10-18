<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Models\User;
use App\Services\PayoutService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A payout may only be requested to complete, well-formed bank or UPI details. Reported on live:
 * a payout went through although no bank account had been added.
 */
class PayoutDestinationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SaasSetting::create(['payout_mode' => 'manual', 'payout_charges' => 40, 'payout_hours' => 24]);
    }

    private function owner(array $details): User
    {
        $u = User::factory()->create(array_merge(['commission_wallet_balance' => 500.00], $details));
        $u->assignRole('turf-admin');

        return $u->fresh();
    }

    private function assertRefused(array $details): void
    {
        $owner = $this->owner($details);
        try {
            (new PayoutService())->requestPayout($owner, 100.00);
            $this->fail('Payout should have been refused for ' . json_encode($details));
        } catch (\Exception $e) {
            $this->assertStringContainsString('Payment details missing or invalid', $e->getMessage());
        }
        $this->assertSame(0, TurfPayout::where('user_id', $owner->id)->count());
        $this->assertEquals(500.00, (float) $owner->fresh()->commission_wallet_balance, 'wallet must not be debited');
    }

    public function test_payout_is_refused_without_usable_details(): void
    {
        $this->assertRefused([]);
        $this->assertRefused(['payout_method' => 'bank']);
        $this->assertRefused(['payout_method' => 'bank', 'bank_account_name' => ' ', 'bank_account_number' => ' ', 'bank_ifsc' => ' ']);
        $this->assertRefused(['payout_method' => 'bank', 'bank_account_number' => '123', 'bank_ifsc' => 'X']);
        $this->assertRefused(['payout_method' => 'bank', 'bank_account_number' => '1234567890', 'bank_ifsc' => 'SBIN0001234']); // no holder name
        $this->assertRefused(['payout_method' => 'bank', 'bank_account_name' => 'Peter Das', 'bank_account_number' => '12AB567890', 'bank_ifsc' => 'SBIN0001234']);
        $this->assertRefused(['payout_method' => 'bank', 'bank_account_name' => 'Peter Das', 'bank_account_number' => '1234567890', 'bank_ifsc' => 'SBIN1001234']); // 5th char must be 0
        $this->assertRefused(['payout_method' => 'upi']);
        $this->assertRefused(['payout_method' => 'upi', 'upi_id' => 'not-a-upi-id']);
        $this->assertRefused(['payout_method' => 'upi', 'bank_account_name' => 'Peter Das', 'bank_account_number' => '1234567890', 'bank_ifsc' => 'SBIN0001234']); // UPI chosen, no UPI ID
        $this->assertRefused(['payout_method' => 'cash']);
    }

    public function test_payout_is_allowed_with_valid_bank_or_upi_details(): void
    {
        $bank = $this->owner(['payout_method' => 'bank', 'bank_account_name' => 'Peter Das', 'bank_account_number' => '9876543210123', 'bank_ifsc' => 'SBIN0001234']);
        $payout = (new PayoutService())->requestPayout($bank, 100.00);
        $this->assertSame('pending', $payout->status);
        $this->assertSame('9876543210123', $payout->account_details['account_number']);

        $upi = $this->owner(['payout_method' => 'upi', 'upi_id' => 'peter.das@okhdfcbank']);
        $this->assertSame('pending', (new PayoutService())->requestPayout($upi, 100.00)->status);

        // Stored in another case / with stray spaces still counts (normalised before checking).
        $legacy = $this->owner(['payout_method' => 'BANK', 'bank_account_name' => ' Peter  Das ', 'bank_account_number' => '9876 5432 10123', 'bank_ifsc' => 'sbin0001234']);
        $this->assertSame('pending', (new PayoutService())->requestPayout($legacy, 100.00)->status);
    }

    public function test_both_payout_screens_reject_malformed_details_and_store_clean_values(): void
    {
        foreach (['turf.banking-manager', 'turf.business-manager'] as $component) {
            $owner = $this->owner([]);
            $this->actingAs($owner);

            Volt::test($component)
                ->set('payoutMethod', 'bank')->set('bankAccountName', 'Peter Das')
                ->set('bankAccountNumber', '123')->set('bankIfsc', 'SBIN0001234')
                ->call('saveKycDetails')
                ->assertHasErrors('bankAccountNumber');

            Volt::test($component)
                ->set('payoutMethod', 'bank')->set('bankAccountName', 'Peter Das')
                ->set('bankAccountNumber', '9876543210123')->set('bankIfsc', 'XYZ')
                ->call('saveKycDetails')
                ->assertHasErrors('bankIfsc');

            Volt::test($component)
                ->set('payoutMethod', 'upi')->set('upiId', 'nonsense')
                ->call('saveKycDetails')
                ->assertHasErrors('upiId');

            $this->assertNull($owner->fresh()->payout_method, "{$component}: nothing may be saved after errors");

            Volt::test($component)
                ->set('payoutMethod', 'bank')->set('bankAccountName', '  Peter   Das ')
                ->set('bankAccountNumber', '9876 5432 10123')->set('bankIfsc', 'sbin0001234')
                ->call('saveKycDetails')
                ->assertHasNoErrors();

            $saved = $owner->fresh();
            $this->assertSame('bank', $saved->payout_method);
            $this->assertSame('Peter Das', $saved->bank_account_name);
            $this->assertSame('9876543210123', $saved->bank_account_number);
            $this->assertSame('SBIN0001234', $saved->bank_ifsc);
        }
    }
}
