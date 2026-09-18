<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaasTurfImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $saasAdmin;
    protected User $turfOwner;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // 1. Create SaaS Admin
        $this->saasAdmin = User::factory()->create([
            'name' => 'SaaS Administrator',
            'email' => 'admin@saas.com',
            'mobile' => '9999999999',
        ]);
        $this->saasAdmin->assignRole('saas-admin');

        // 2. Create Turf Owner
        $this->turfOwner = User::factory()->create([
            'name' => 'Rohit Sharma',
            'email' => 'rohit@turf.com',
            'mobile' => '9888888888',
        ]);
        $this->turfOwner->assignRole('turf-admin');

        // 3. Create Venue & Turf
        $location = Location::create([
            'name' => 'Wankhede Arena',
            'user_id' => $this->turfOwner->id,
            'address' => 'Churchgate, Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Center Pitch',
            'type' => 'Cricket',
            'address' => 'Churchgate, Mumbai',
            'status' => 'Approved',
            'is_active' => true,
        ]);
    }

    public function test_non_saas_admin_cannot_access_impersonation_route(): void
    {
        $regularUser = User::factory()->create();
        $regularUser->assignRole('customer');

        $response = $this->actingAs($regularUser)->get(route('saas.turfs.impersonate', $this->turf->id));
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_impersonate_turf_owner_and_is_redirected_to_turf_dashboard(): void
    {
        $response = $this->actingAs($this->saasAdmin)->get(route('saas.turfs.impersonate', $this->turf->id));

        $response->assertRedirect(route('turf.dashboard'));

        // Assert user in session is now the turf owner
        $this->assertEquals($this->turfOwner->id, auth()->id());

        // Assert session has impersonation and turf context keys
        $this->assertEquals($this->saasAdmin->id, session('impersonator_id'));
        $this->assertEquals($this->turf->id, session('active_turf_id'));
        $this->assertEquals($this->turf->location_id, session('active_location_id'));
    }

    public function test_saas_admin_can_leave_impersonation_and_restore_original_account(): void
    {
        // First, impersonate
        $this->actingAs($this->saasAdmin)->get(route('saas.turfs.impersonate', $this->turf->id));
        $this->assertEquals($this->turfOwner->id, auth()->id());

        // Then, leave impersonation
        $response = $this->get(route('saas.impersonation.leave'));

        $response->assertRedirect(route('saas.turfs'));

        // Assert original SaaS Admin is logged back in
        $this->assertEquals($this->saasAdmin->id, auth()->id());

        // Assert impersonator session keys are cleared
        $this->assertNull(session('impersonator_id'));
    }

    public function test_saas_turfs_directory_page_renders_with_turfs_and_new_tab_links(): void
    {
        $response = $this->actingAs($this->saasAdmin)->get(route('saas.turfs'));

        $response->assertStatus(200);
        $response->assertSee('Turfs & Locations Directory', false);
        $response->assertSee('Center Pitch');
        $response->assertSee('Rohit Sharma');
        $response->assertSee('Access as Owner');
        $response->assertSee('target="_blank"', false);
    }

    public function test_saas_turfs_directory_livewire_search_filters_correctly(): void
    {
        Livewire::actingAs($this->saasAdmin)
            ->test('saas.turf-directory')
            ->assertSee('Center Pitch')
            ->set('search', 'NonExistentTurfNameXYZ')
            ->assertDontSee('Center Pitch')
            ->assertSee('No Turfs Found');
    }
}
