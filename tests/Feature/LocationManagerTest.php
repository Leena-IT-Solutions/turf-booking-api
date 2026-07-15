<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LocationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/turf/locations');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_locations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/turf/locations');
        $response->assertStatus(403);
    }

    public function test_turf_admin_can_access_locations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('turf-admin');

        $response = $this->actingAs($user)->get('/turf/locations');
        $response->assertOk();
    }

    public function test_manager_can_access_locations(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $response = $this->actingAs($user)->get('/turf/locations');
        $response->assertOk();
    }

    public function test_user_can_create_location(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        $component = Volt::test('turf.location-manager')
            ->set('name', 'Bandra Turf')
            ->set('address', 'Bandra BKC Complex, Mumbai')
            ->set('latitude', 19.0682)
            ->set('longitude', 72.8703)
            ->set('contact_number', '+91 9999988888')
            ->set('email', 'bandra@turf.com')
            ->call('saveLocation');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'name' => 'Bandra Turf',
            'address' => 'Bandra BKC Complex, Mumbai',
            'latitude' => 19.0682,
            'longitude' => 72.8703,
            'contact_number' => '+91 9999988888',
            'email' => 'bandra@turf.com',
        ]);
    }

    public function test_location_validation_rules(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        // Name and address are required
        Volt::test('turf.location-manager')
            ->set('name', '')
            ->set('address', '')
            ->call('saveLocation')
            ->assertHasErrors(['name' => 'required', 'address' => 'required']);

        // Latitude/longitude boundaries validation
        Volt::test('turf.location-manager')
            ->set('name', 'Valid Name')
            ->set('address', 'Valid Address')
            ->set('latitude', '120.00') // Out of range -90 to 90
            ->set('longitude', '-200.00') // Out of range -180 to 180
            ->call('saveLocation')
            ->assertHasErrors(['latitude', 'longitude']);
    }

    public function test_user_can_update_location(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        $location = Location::create([
            'name' => 'Old Arena',
            'address' => 'Old Address',
            'latitude' => 15.0,
            'longitude' => 73.0,
            'contact_number' => '123456',
            'email' => 'old@arena.com',
        ]);

        $component = Volt::test('turf.location-manager')
            ->call('editLocation', $location->id)
            ->set('name', 'New Arena')
            ->set('address', 'New Address')
            ->set('latitude', 16.123456)
            ->set('longitude', 74.654321)
            ->set('contact_number', '654321')
            ->set('email', 'new@arena.com')
            ->call('saveLocation');

        $component->assertHasNoErrors();

        $updated = $location->fresh();
        $this->assertEquals('New Arena', $updated->name);
        $this->assertEquals('New Address', $updated->address);
        $this->assertEquals(16.123456, $updated->latitude);
        $this->assertEquals(74.654321, $updated->longitude);
        $this->assertEquals('654321', $updated->contact_number);
        $this->assertEquals('new@arena.com', $updated->email);
    }

    public function test_user_can_delete_location(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin);

        $location = Location::create([
            'name' => 'To Be Deleted',
            'address' => 'Temp Address',
        ]);

        $component = Volt::test('turf.location-manager')
            ->call('confirmDelete', $location->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete');

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_user_can_autofill_own_contact_details(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
            'email' => 'testuser@example.com',
        ]);
        $user->assignRole('turf-admin');

        $this->actingAs($user);

        Volt::test('turf.location-manager')
            ->call('useOwnDetails')
            ->assertSet('contact_number', '9876543210')
            ->assertSet('email', 'testuser@example.com');
    }
}
