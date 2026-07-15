<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurfAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_turf_pages(): void
    {
        $this->get('/turf/dashboard')->assertRedirect('/login');
        $this->get('/turf/bookings')->assertRedirect('/login');
        $this->get('/turf/settings')->assertRedirect('/login');
        $this->get('/turf/offers')->assertRedirect('/login');
    }

    public function test_non_turf_admin_cannot_access_turf_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user)->get('/turf/dashboard')->assertStatus(403);
        $this->actingAs($user)->get('/turf/bookings')->assertStatus(403);
        $this->actingAs($user)->get('/turf/settings')->assertStatus(403);
        $this->actingAs($user)->get('/turf/offers')->assertStatus(403);
    }

    public function test_turf_admin_can_access_turf_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin)->get('/turf/dashboard')->assertOk();
        $this->actingAs($admin)->get('/turf/bookings')->assertOk();
        $this->actingAs($admin)->get('/turf/settings')->assertOk();
        $this->actingAs($admin)->get('/turf/offers')->assertOk();
    }
}
