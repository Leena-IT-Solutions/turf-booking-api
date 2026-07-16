<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorPageTest extends TestCase
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

    public function test_saas_admin_is_redirected_to_administrator_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertRedirect('/saas/administrator');
    }

    public function test_turf_admin_is_redirected_to_turf_dashboard(): void
    {
        $turfAdmin = User::factory()->create();
        $turfAdmin->assignRole('turf-admin');

        $response = $this->actingAs($turfAdmin)->get('/dashboard');
        $response->assertRedirect('/turf/dashboard');
    }

    public function test_customer_stays_on_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $response = $this->actingAs($customer)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Initially kept blank.');
    }

    public function test_user_with_customer_and_saas_admin_role_is_redirected_to_saas_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect('/saas/administrator');
    }

    public function test_guest_cannot_access_administrator_route(): void
    {
        $response = $this->get('/saas/administrator');
        $response->assertRedirect('/login');
    }

    public function test_non_saas_admin_cannot_access_administrator_route(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $response = $this->actingAs($customer)->get('/saas/administrator');
        $response->assertStatus(403);

        $turfAdmin = User::factory()->create();
        $turfAdmin->assignRole('turf-admin');

        $response = $this->actingAs($turfAdmin)->get('/saas/administrator');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_administrator_route(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $response = $this->actingAs($admin)->get('/saas/administrator');
        $response->assertOk();
        $response->assertSee('System Updates');
    }
}
