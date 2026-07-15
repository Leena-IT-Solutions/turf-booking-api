<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\TurfPhoto;
use App\Models\Facility;
use App\Models\TurfFacility;
use App\Models\Equipment;
use App\Models\TurfEquipment;
use App\Models\Sport;
use App\Models\TurfSport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TurfSubEntitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Turf $turf;
    private Facility $facility1;
    private Facility $facility2;
    private Equipment $equipment1;
    private Equipment $equipment2;
    private Sport $sport1;
    private Sport $sport2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Setup user, location, and turf
        $this->user = User::factory()->create();
        $this->user->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->user->id,
            'name' => 'Bandra Arena',
            'address' => '123 Bandra West, Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Court A',
            'type' => 'Synthetic',
            'is_active' => true,
        ]);

        // Seed master options
        $this->facility1 = Facility::create(['name' => 'Locker Room', 'is_active' => true]);
        $this->facility2 = Facility::create(['name' => 'VIP Changing Room', 'is_active' => true]);

        $this->equipment1 = Equipment::create(['name' => 'FIFA Pro Soccer Balls', 'is_active' => true]);
        $this->equipment2 = Equipment::create(['name' => 'Training Cones', 'is_active' => true]);

        $this->sport1 = Sport::create(['name' => 'Box Cricket', 'is_active' => true]);
        $this->sport2 = Sport::create(['name' => 'Indoor Football', 'is_active' => true]);
    }

    private function testComponent(string $name)
    {
        $this->startSession();
        session(['active_turf_id' => $this->turf->id]);
        return Livewire::test($name);
    }

    public function test_photos_crud_functionality(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user);

        // 1. Initial Empty State
        $this->testComponent('turf.photos-manager')
            ->assertSee('No Photos Uploaded')
            ->assertDontSee('turf_photos/');

        // 2. Upload Photo
        $file = UploadedFile::fake()->image('field.jpg');

        $this->testComponent('turf.photos-manager')
            ->set('photoFile', $file)
            ->call('uploadPhoto')
            ->assertHasNoErrors()
            ->assertSee('Photo uploaded successfully.');

        $photo = TurfPhoto::first();
        $this->assertNotNull($photo);
        $this->assertTrue($photo->is_active);
        Storage::disk('public')->assertExists($photo->photo);

        // 3. Toggle Active
        $this->testComponent('turf.photos-manager')
            ->call('toggleActive', $photo->id);

        $this->assertFalse($photo->fresh()->is_active);

        // 4. Delete Photo
        $this->testComponent('turf.photos-manager')
            ->call('confirmDelete', $photo->id)
            ->assertSet('deletingId', $photo->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete')
            ->assertSee('Photo deleted successfully.');

        $this->assertDatabaseMissing('turf_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->photo);
    }

    public function test_facilities_crud_functionality(): void
    {
        $this->actingAs($this->user);

        // 1. Create Facility Link
        $this->testComponent('turf.facilities-manager')
            ->call('openCreateModal')
            ->set('facility_id', $this->facility1->id)
            ->set('is_active', true)
            ->call('saveFacility')
            ->assertHasNoErrors()
            ->assertSee('Facility added successfully.');

        $this->assertDatabaseHas('turf_facilities', [
            'turf_id' => $this->turf->id,
            'facility_id' => $this->facility1->id,
            'is_active' => true,
        ]);

        $facility = TurfFacility::first();

        // 2. Edit Facility Link
        $this->testComponent('turf.facilities-manager')
            ->call('openEditModal', $facility->id)
            ->assertSet('facility_id', $this->facility1->id)
            ->set('facility_id', $this->facility2->id)
            ->call('saveFacility')
            ->assertHasNoErrors()
            ->assertSee('Facility updated successfully.');

        $this->assertEquals($this->facility2->id, $facility->fresh()->facility_id);

        // 3. Toggle Active
        $this->testComponent('turf.facilities-manager')
            ->call('toggleActive', $facility->id);

        $this->assertFalse($facility->fresh()->is_active);

        // 4. Delete Facility Link
        $this->testComponent('turf.facilities-manager')
            ->call('confirmDelete', $facility->id)
            ->call('performDelete')
            ->assertSee('Facility deleted successfully.');

        $this->assertDatabaseMissing('turf_facilities', ['id' => $facility->id]);
    }

    public function test_equipments_crud_functionality(): void
    {
        $this->actingAs($this->user);

        // 1. Create Equipment Link
        $this->testComponent('turf.equipments-manager')
            ->call('openCreateModal')
            ->set('equipment_id', $this->equipment1->id)
            ->set('is_active', true)
            ->call('saveEquipment')
            ->assertHasNoErrors()
            ->assertSee('Equipment added successfully.');

        $this->assertDatabaseHas('turf_equipments', [
            'turf_id' => $this->turf->id,
            'equipment_id' => $this->equipment1->id,
            'is_active' => true,
        ]);

        $equipment = TurfEquipment::first();

        // 2. Edit Equipment Link
        $this->testComponent('turf.equipments-manager')
            ->call('openEditModal', $equipment->id)
            ->assertSet('equipment_id', $this->equipment1->id)
            ->set('equipment_id', $this->equipment2->id)
            ->call('saveEquipment')
            ->assertHasNoErrors()
            ->assertSee('Equipment updated successfully.');

        $this->assertEquals($this->equipment2->id, $equipment->fresh()->equipment_id);

        // 3. Toggle Active
        $this->testComponent('turf.equipments-manager')
            ->call('toggleActive', $equipment->id);

        $this->assertFalse($equipment->fresh()->is_active);

        // 4. Delete Equipment Link
        $this->testComponent('turf.equipments-manager')
            ->call('confirmDelete', $equipment->id)
            ->call('performDelete')
            ->assertSee('Equipment deleted successfully.');

        $this->assertDatabaseMissing('turf_equipments', ['id' => $equipment->id]);
    }

    public function test_sports_crud_functionality(): void
    {
        $this->actingAs($this->user);

        // 1. Create Sport Link
        $this->testComponent('turf.sports-manager')
            ->call('openCreateModal')
            ->set('sport_id', $this->sport1->id)
            ->set('is_active', true)
            ->call('saveSport')
            ->assertHasNoErrors()
            ->assertSee('Sport added successfully.');

        $this->assertDatabaseHas('turf_sports', [
            'turf_id' => $this->turf->id,
            'sport_id' => $this->sport1->id,
            'is_active' => true,
        ]);

        $sport = TurfSport::first();

        // 2. Edit Sport Link
        $this->testComponent('turf.sports-manager')
            ->call('openEditModal', $sport->id)
            ->assertSet('sport_id', $this->sport1->id)
            ->set('sport_id', $this->sport2->id)
            ->call('saveSport')
            ->assertHasNoErrors()
            ->assertSee('Sport updated successfully.');

        $this->assertEquals($this->sport2->id, $sport->fresh()->sport_id);

        // 3. Toggle Active
        $this->testComponent('turf.sports-manager')
            ->call('toggleActive', $sport->id);

        $this->assertFalse($sport->fresh()->is_active);

        // 4. Delete Sport Link
        $this->testComponent('turf.sports-manager')
            ->call('confirmDelete', $sport->id)
            ->call('performDelete')
            ->assertSee('Sport deleted successfully.');

        $this->assertDatabaseMissing('turf_sports', ['id' => $sport->id]);
    }

    public function test_unauthorized_users_cannot_modify_other_users_turf_entities(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('turf-admin');
        $this->actingAs($otherUser);

        // Try to toggle active status on a TurfPhoto of user 1
        $photo = TurfPhoto::create([
            'turf_id' => $this->turf->id,
            'photo' => 'turf_photos/somefile.png',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->testComponent('turf.photos-manager')
            ->call('toggleActive', $photo->id);
    }
}
