<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayCharge;
use App\Models\User;
use Database\Seeders\PaymentGatewayChargeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PaymentGatewayChargesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/saas/payment-gateway-charges');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/payment-gateway-charges');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_payment_gateway_charges_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin)
            ->get('/saas/payment-gateway-charges')
            ->assertOk();
    }

    public function test_can_seed_and_display_payment_gateway_charges(): void
    {
        $this->seed(PaymentGatewayChargeSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.payment-gateway-charges')
            ->assertSee('UPI')
            ->assertSee('Net Banking')
            ->assertSee('Credit Cards (Domestic)')
            ->assertSee('2.36%')
            ->assertSee('3.54%');
    }

    public function test_can_create_payment_gateway_charge(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.payment-gateway-charges')
            ->set('name', 'Corporate Credit Cards')
            ->set('code', 'corp_credit_card')
            ->set('charge_percentage', '3.50')
            ->set('tax_percentage', '18.00')
            ->set('total_percentage', '4.1300')
            ->set('flat_fee', '0.00')
            ->set('description', 'Commercial and corporate cards')
            ->set('is_active', true)
            ->set('sort_order', 10)
            ->call('saveCharge')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_gateway_charges', [
            'name' => 'Corporate Credit Cards',
            'code' => 'corp_credit_card',
            'charge_percentage' => 3.50,
            'tax_percentage' => 18.00,
            'total_percentage' => 4.1300,
        ]);
    }

    public function test_can_update_payment_gateway_charge(): void
    {
        $charge = PaymentGatewayCharge::create([
            'name' => 'Test Method',
            'code' => 'test_method',
            'charge_percentage' => 2.00,
            'tax_percentage' => 18.00,
            'total_percentage' => 2.3600,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.payment-gateway-charges')
            ->call('openEditModal', $charge->id)
            ->set('charge_percentage', '2.50')
            ->set('tax_percentage', '18.00')
            ->call('recalculateTotal')
            ->call('saveCharge')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_gateway_charges', [
            'id' => $charge->id,
            'charge_percentage' => 2.50,
            'total_percentage' => 2.9500,
        ]);
    }

    public function test_can_toggle_is_active(): void
    {
        $charge = PaymentGatewayCharge::create([
            'name' => 'Toggle Method',
            'code' => 'toggle_method',
            'charge_percentage' => 2.00,
            'tax_percentage' => 18.00,
            'total_percentage' => 2.3600,
            'is_active' => true,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.payment-gateway-charges')
            ->call('toggleActive', $charge->id)
            ->assertHasNoErrors();

        $this->assertFalse($charge->fresh()->is_active);

        Volt::test('saas.payment-gateway-charges')
            ->call('toggleActive', $charge->id)
            ->assertHasNoErrors();

        $this->assertTrue($charge->fresh()->is_active);
    }

    public function test_can_delete_payment_gateway_charge(): void
    {
        $charge = PaymentGatewayCharge::create([
            'name' => 'To Delete',
            'code' => 'to_delete',
            'charge_percentage' => 2.00,
            'tax_percentage' => 18.00,
            'total_percentage' => 2.3600,
            'is_active' => true,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.payment-gateway-charges')
            ->set('deletingId', $charge->id)
            ->call('deleteCharge')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('payment_gateway_charges', [
            'id' => $charge->id,
        ]);
    }
}
