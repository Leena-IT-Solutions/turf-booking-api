<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CustomerTurfPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_customer_user_can_access_dashboard_and_sees_turf_prompter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
        $response->assertSeeLivewire('dashboard.turf-prompter');
    }

    public function test_customer_can_claim_turf_admin_and_manager_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user);

        // Run the Volt component
        Volt::test('dashboard.turf-prompter')
            ->call('claimTurfAdmin')
            ->assertRedirect(route('turf.dashboard'));

        // Refresh user and assert roles
        $user = $user->fresh();
        $this->assertTrue($user->hasRole('turf-admin'));
        $this->assertTrue($user->hasRole('manager'));
    }

    public function test_user_with_turf_admin_and_manager_roles_can_access_dashboard_root(): void
    {
        $user = User::factory()->create();
        $user->assignRole('turf-admin', 'manager');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
    }

    public function test_user_with_customer_and_turf_admin_roles_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $user->assignRole('turf-admin');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
    }

    public function test_user_with_customer_and_manager_roles_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $user->assignRole('manager');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
    }

    public function test_user_with_customer_manager_and_saas_admin_roles_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $user->assignRole('manager');
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
    }
}
