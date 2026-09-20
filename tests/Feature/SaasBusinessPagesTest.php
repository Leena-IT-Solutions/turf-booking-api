<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasBusinessPagesTest extends TestCase
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

        $this->turfAdmin = User::factory()->create();
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_saas_admin_can_access_subscriptions_page(): void
    {
        $response = $this->actingAs($this->saasAdmin)->get(route('saas.subscriptions'));
        $response->assertStatus(200);
        $response->assertSee('Subscription Revenue');
    }

    public function test_saas_admin_can_access_commissions_page(): void
    {
        $response = $this->actingAs($this->saasAdmin)->get(route('saas.commissions'));
        $response->assertStatus(200);
        $response->assertSee('Platform Earnings & Commission', false);
        $response->assertSee('Platform Fees');
        $response->assertSee('Cancellation Fees');
    }

    public function test_saas_admin_can_access_payouts_page(): void
    {
        $response = $this->actingAs($this->saasAdmin)->get(route('saas.payouts'));
        $response->assertStatus(200);
        $response->assertSee('Turf Payouts');
    }

    public function test_turf_admin_cannot_access_saas_business_pages(): void
    {
        $this->actingAs($this->turfAdmin)->get(route('saas.subscriptions'))->assertStatus(403);
        $this->actingAs($this->turfAdmin)->get(route('saas.commissions'))->assertStatus(403);
        $this->actingAs($this->turfAdmin)->get(route('saas.payouts'))->assertStatus(403);
    }
}
