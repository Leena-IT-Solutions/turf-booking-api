<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlankPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_unauthorized_roles_and_guests_cannot_access_blank_pages(): void
    {
        $pages = [
            'turf.photos',
            'turf.facilities',
            'turf.equipments',
            'turf.sports',
        ];

        foreach ($pages as $page) {
            // Guest redirected
            $response = $this->get(route($page));
            $response->assertRedirect(route('login'));

            // Customer forbidden/redirected (role middleware throws an exception or aborts)
            $customer = User::factory()->create();
            $customer->assignRole('customer');
            $this->actingAs($customer);

            $response = $this->get(route($page));
            $response->assertStatus(403);

            // Log out
            auth()->logout();
        }
    }

    public function test_turf_admin_and_managers_can_access_blank_pages(): void
    {
        $pages = [
            'turf.photos',
            'turf.facilities',
            'turf.equipments',
            'turf.sports',
        ];

        $roles = ['turf-admin', 'manager'];

        foreach ($roles as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);

            foreach ($pages as $page) {
                $response = $this->get(route($page));
                $response->assertOk();
            }

            auth()->logout();
        }
    }
}
