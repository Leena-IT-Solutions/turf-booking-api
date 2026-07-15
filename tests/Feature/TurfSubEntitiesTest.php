<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\TurfPhoto;
use App\Models\Facility;
use App\Models\Equipment;
use App\Models\Sport;
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

    public function test_facilities_selection_sync(): void
    {
        $this->actingAs($this->user);

        // 1. Initially none selected
        $this->testComponent('turf.facilities-manager')
            ->assertSet('selectedFacilityIds', [])
            ->assertSee('Locker Room')
            ->assertSee('VIP Changing Room');

        // 2. Sync select options
        $this->testComponent('turf.facilities-manager')
            ->set('selectedFacilityIds', [(string)$this->facility1->id, (string)$this->facility2->id])
            ->call('saveFacilities')
            ->assertHasNoErrors()
            ->assertSee('Facilities updated successfully.');

        $this->assertDatabaseHas('facility_turf', [
            'turf_id' => $this->turf->id,
            'facility_id' => $this->facility1->id,
        ]);
        $this->assertDatabaseHas('facility_turf', [
            'turf_id' => $this->turf->id,
            'facility_id' => $this->facility2->id,
        ]);

        // 3. Deselect facility 2
        $this->testComponent('turf.facilities-manager')
            ->set('selectedFacilityIds', [(string)$this->facility1->id])
            ->call('saveFacilities')
            ->assertHasNoErrors()
            ->assertSee('Facilities updated successfully.');

        $this->assertDatabaseMissing('facility_turf', [
            'turf_id' => $this->turf->id,
            'facility_id' => $this->facility2->id,
        ]);
    }

    public function test_equipments_selection_sync(): void
    {
        $this->actingAs($this->user);

        // 1. Initially none selected
        $this->testComponent('turf.equipments-manager')
            ->assertSet('selectedEquipmentIds', [])
            ->assertSee('FIFA Pro Soccer Balls')
            ->assertSee('Training Cones');

        // 2. Sync select options
        $this->testComponent('turf.equipments-manager')
            ->set('selectedEquipmentIds', [(string)$this->equipment1->id])
            ->call('saveEquipments')
            ->assertHasNoErrors()
            ->assertSee('Equipments updated successfully.');

        $this->assertDatabaseHas('equipment_turf', [
            'turf_id' => $this->turf->id,
            'equipment_id' => $this->equipment1->id,
        ]);
    }

    public function test_sports_selection_sync(): void
    {
        $this->actingAs($this->user);

        // 1. Initially none selected
        $this->testComponent('turf.sports-manager')
            ->assertSet('selectedSportIds', [])
            ->assertSee('Box Cricket')
            ->assertSee('Indoor Football');

        // 2. Sync select options
        $this->testComponent('turf.sports-manager')
            ->set('selectedSportIds', [(string)$this->sport1->id])
            ->call('saveSports')
            ->assertHasNoErrors()
            ->assertSee('Sports updated successfully.');

        $this->assertDatabaseHas('sport_turf', [
            'turf_id' => $this->turf->id,
            'sport_id' => $this->sport1->id,
        ]);
    }
}
