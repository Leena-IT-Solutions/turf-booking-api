<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BusinessPageTest extends TestCase
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

            'commission_wallet_balance' => 1500.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_turf_admin_can_access_business_page()
    {
        $response = $this->actingAs($this->turfAdmin)->get('/turf/business');
        $response->assertStatus(200);
    }

    public function test_business_page_renders_wallet_badges_and_forms()
    {
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.business-manager')
            ->assertSee('AVAILABLE FOR WITHDRAWAL')
            ->assertSee('Wallet Statement & Commission Ledger', false);
    }

    public function test_wallet_statement_loads_more_rows_on_scroll_instead_of_paginating()
    {
        $this->actingAs($this->turfAdmin);

        foreach (range(1, 15) as $i) {
            \App\Models\CommissionWalletTransaction::create([
                'user_id' => $this->turfAdmin->id,
                'type' => 'commission_debit',
                'amount' => -1.00,
                'balance_after' => 0.00,
                'description' => "Marker TX {$i}",
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        $component = Volt::test('turf.business-manager');

        // Initially only the 10 most recent (TX 15 down to TX 6) are shown, with an
        // auto-load-more trigger instead of numbered page links.
        $component->assertSee('Marker TX 15', false)
            ->assertSee('Marker TX 6', false)
            ->assertDontSee('Marker TX 5', false)
            ->assertSee('Loading more', false)
            ->assertDontSee('End of statement', false);

        $component->call('loadMoreTx');

        // After loading more, all 15 rows are visible and the end-of-list marker shows instead.
        $component->assertSee('Marker TX 1', false)
            ->assertSee('End of statement', false);
    }
}
