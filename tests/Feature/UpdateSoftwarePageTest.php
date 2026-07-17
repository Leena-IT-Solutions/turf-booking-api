<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateSoftwarePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_update_software_route(): void
    {
        $response = $this->get('/saas/update-software');
        $response->assertRedirect('/login');
    }

    public function test_non_saas_admin_cannot_access_update_software_route(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $response = $this->actingAs($customer)->get('/saas/update-software');
        $response->assertStatus(403);

        $turfAdmin = User::factory()->create();
        $turfAdmin->assignRole('turf-admin');

        $response = $this->actingAs($turfAdmin)->get('/saas/update-software');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_update_software_route(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $response = $this->actingAs($admin)->get('/saas/update-software');
        $response->assertOk();
        $response->assertSee('Update Software');
        $response->assertSee('System Updates');
    }
}
