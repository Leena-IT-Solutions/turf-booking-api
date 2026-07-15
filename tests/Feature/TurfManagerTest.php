<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/turf/turfs');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_turfs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/turf/turfs');
        $response->assertStatus(403);
    }

    public function test_turf_admin_can_access_turfs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('turf-admin');

        $response = $this->actingAs($user)->get('/turf/turfs');
        $response->assertOk();
    }

    public function test_manager_can_access_turfs(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $response = $this->actingAs($user)->get('/turf/turfs');
        $response->assertOk();
    }

    public function test_user_can_create_turf(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        $location = Location::create([
            'user_id' => $admin->id,
            'name' => 'Admin Location',
            'address' => 'Bandra, Mumbai',
        ]);

        $component = Volt::test('turf.turf-manager')
            ->set('location_id', $location->id)
            ->set('name', 'Turf Alpha')
            ->set('type', 'Synthetic')
            ->set('area', '8,000 sq ft')
            ->set('description', 'Cool description')
            ->set('equipments', 'Ball, Goals')
            ->call('saveTurf');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('turfs', [
            'location_id' => $location->id,
            'name' => 'Turf Alpha',
            'type' => 'Synthetic',
            'area' => '8,000 sq ft',
            'description' => 'Cool description',
            'equipments' => 'Ball, Goals',
            'is_active' => 1,
        ]);
    }

    public function test_turf_validation_rules(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        Volt::test('turf.turf-manager')
            ->set('name', '')
            ->set('location_id', '')
            ->set('type', 'InvalidType')
            ->call('saveTurf')
            ->assertHasErrors(['name' => 'required', 'location_id' => 'required', 'type' => 'in']);
    }

    public function test_user_can_update_turf(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        $location = Location::create([
            'user_id' => $admin->id,
            'name' => 'Admin Location',
            'address' => 'Bandra, Mumbai',
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Old Turf Name',
            'type' => 'Synthetic',
            'area' => '5,000 sq ft',
        ]);

        $component = Volt::test('turf.turf-manager')
            ->call('editTurf', $turf->id)
            ->set('name', 'New Turf Name')
            ->set('type', 'Hard')
            ->set('area', '6,000 sq ft')
            ->call('saveTurf');

        $component->assertHasNoErrors();

        $updated = $turf->fresh();
        $this->assertEquals('New Turf Name', $updated->name);
        $this->assertEquals('Hard', $updated->type);
        $this->assertEquals('6,000 sq ft', $updated->area);
    }

    public function test_user_can_delete_turf(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        $location = Location::create([
            'user_id' => $admin->id,
            'name' => 'Admin Location',
            'address' => 'Bandra, Mumbai',
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf to Delete',
            'type' => 'Other',
        ]);

        $component = Volt::test('turf.turf-manager')
            ->call('confirmDelete', $turf->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete');

        $this->assertDatabaseMissing('turfs', ['id' => $turf->id]);
    }

    public function test_user_cannot_see_or_manipulate_other_users_turfs(): void
    {
        $userA = User::factory()->create();
        $userA->assignRole('turf-admin');
        $locationA = Location::create([
            'user_id' => $userA->id,
            'name' => 'Location A',
            'address' => 'Bandra, Mumbai',
        ]);
        $turfA = Turf::create([
            'location_id' => $locationA->id,
            'name' => 'UniqueTurfX',
            'type' => 'Synthetic',
        ]);

        $userB = User::factory()->create();
        $userB->assignRole('turf-admin');
        $this->actingAs($userB);

        Volt::test('turf.turf-manager')
            ->assertDontSee('UniqueTurfX');

        $this->assertThrows(function () use ($turfA) {
            Volt::test('turf.turf-manager')
                ->call('editTurf', $turfA->id);
        }, \Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->assertThrows(function () use ($turfA) {
            Volt::test('turf.turf-manager')
                ->call('deleteTurf', $turfA->id);
        }, \Illuminate\Database\Eloquent\ModelNotFoundException::class);
    }
}
