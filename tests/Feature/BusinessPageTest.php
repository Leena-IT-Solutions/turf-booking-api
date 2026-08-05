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
            ->assertSee('Request Payout')
            ->assertSee('Payout Receiving Details');
    }

}
