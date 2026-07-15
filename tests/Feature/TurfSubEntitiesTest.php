<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\TurfPhoto;
use App\Models\Facility;
use App\Models\Equipment;
use App\Models\Sport;
use App\Models\Slot;
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

    public function test_slots_selection_sync(): void
    {
        $this->actingAs($this->user);

        // Seed some master slots
        $category = \App\Models\SlotCategory::create(['name' => 'Midnight', 'is_active' => true, 'sort_order' => 1]);
        $slot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '00:00:00',
            'to_time' => '00:30:00',
            'duration' => 30,
            'is_active' => true,
        ]);
        $slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '00:30:00',
            'to_time' => '01:00:00',
            'duration' => 30,
            'is_active' => true,
        ]);

        // 1. Initially none selected
        $this->testComponent('turf.slots-manager')
            ->assertSet('selectedSlotIds', [])
            ->assertSee('12:00 AM')
            ->assertSee('12:30 AM');

        // 2. Sync select options
        $this->testComponent('turf.slots-manager')
            ->set('selectedSlotIds', [(string)$slot1->id])
            ->call('saveSlots')
            ->assertHasNoErrors()
            ->assertSee('Slots configuration updated successfully.');

        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot1->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot2->id,
            'is_active' => false,
        ]);

        // 3. Select slot2 and deselect slot1
        $this->testComponent('turf.slots-manager')
            ->set('selectedSlotIds', [(string)$slot2->id])
            ->call('saveSlots')
            ->assertHasNoErrors()
            ->assertSee('Slots configuration updated successfully.');

        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot1->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot2->id,
            'is_active' => true,
        ]);

        // 4. Test selectAll()
        $this->testComponent('turf.slots-manager')
            ->call('selectAll')
            ->assertSet('selectedSlotIds', [(string)$slot1->id, (string)$slot2->id]);

        // 5. Test deselectAll()
        $this->testComponent('turf.slots-manager')
            ->call('selectAll')
            ->call('deselectAll')
            ->assertSet('selectedSlotIds', []);

        // 6. Test selectCategorySlots()
        $this->testComponent('turf.slots-manager')
            ->call('selectCategorySlots', $category->id)
            ->assertSet('selectedSlotIds', [(string)$slot1->id, (string)$slot2->id]);

        // 7. Test deselectCategorySlots()
        $this->testComponent('turf.slots-manager')
            ->call('selectAll')
            ->call('deselectCategorySlots', $category->id)
            ->assertSet('selectedSlotIds', []);
    }

    public function test_pricing_page_loads_and_displays_active_slots_pricing(): void
    {
        $this->actingAs($this->user);

        // Seed some master slot categories and slots
        $category = \App\Models\SlotCategory::create(['name' => 'Morning', 'is_active' => true, 'sort_order' => 2]);
        $slot = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '07:00:00',
            'to_time' => '08:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        // Link slot to turf with active status and specific prices
        $this->turf->slots()->attach($slot->id, [
            'is_active' => true,
            'mon' => 500.00,
            'tue' => 500.00,
            'wed' => 500.00,
            'thu' => 550.00,
            'fri' => 600.00,
            'sat' => 800.00,
            'sun' => 900.00,
        ]);

        $this->testComponent('turf.pricing-manager')
            ->assertSee('Pricing Wizard')
            ->assertSee('Q1. Do you have the same rate throughout the week days?')
            ->assertSee('Current Slot Rates')
            ->assertSee('Morning')
            ->assertSee('07:00 AM')
            ->assertSee('₹500')
            ->assertSee('₹800')
            ->assertSee('₹900');
    }

    public function test_pricing_wizard_flat_rate_sync(): void
    {
        $this->actingAs($this->user);

        $category = \App\Models\SlotCategory::create(['name' => 'Morning', 'is_active' => true, 'sort_order' => 2]);
        $slot = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '07:00:00',
            'to_time' => '08:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $this->turf->slots()->attach($slot->id, ['is_active' => true]);

        $this->testComponent('turf.pricing-manager')
            ->set('sameRateThroughoutWeek', 'yes')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('sameRateThroughoutDayAll', 'yes')
            ->set('flatRateAll', '650')
            ->call('applyPricing')
            ->assertHasNoErrors()
            ->assertSee('Pricing rules updated and applied successfully.');

        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot->id,
            'mon' => 650.00,
            'sun' => 650.00,
        ]);

        // Verify wizard configuration is persisted in the turf's pricing_wizard_data JSON field
        $this->assertEquals('yes', $this->turf->fresh()->pricing_wizard_data['sameRateThroughoutWeek']);
        $this->assertEquals('650', $this->turf->fresh()->pricing_wizard_data['flatRateAll']);
    }

    public function test_pricing_wizard_custom_day_groups_dynamic_ranges_sync(): void
    {
        $this->actingAs($this->user);

        $category = \App\Models\SlotCategory::create(['name' => 'Afternoon', 'is_active' => true, 'sort_order' => 3]);
        $slot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '12:00:00',
            'to_time' => '13:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);
        $slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '16:00:00',
            'to_time' => '17:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $this->turf->slots()->attach($slot1->id, ['is_active' => true]);
        $this->turf->slots()->attach($slot2->id, ['is_active' => true]);

        $dayGroupsConfig = [
            [
                'days' => ['mon', 'wed', 'fri'],
                'sameRateThroughoutDay' => 'no',
                'flatRate' => '',
                'timeRanges' => [
                    ['from' => '12:00', 'to' => '14:00', 'rate' => '450'],
                    ['from' => '15:00', 'to' => '18:00', 'rate' => '750'],
                ]
            ],
            [
                'days' => ['sat', 'sun'],
                'sameRateThroughoutDay' => 'yes',
                'flatRate' => '1000',
                'timeRanges' => []
            ]
        ];

        $this->testComponent('turf.pricing-manager')
            ->set('sameRateThroughoutWeek', 'no')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('dayGroups', $dayGroupsConfig)
            ->call('applyPricing')
            ->assertHasNoErrors()
            ->assertSee('Pricing rules updated and applied successfully.');

        // Assert slot 1 rates
        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot1->id,
            'mon' => 450.00,
            'wed' => 450.00,
            'fri' => 450.00,
            'sat' => 1000.00,
            'sun' => 1000.00,
            'tue' => null, // not in day groups
        ]);

        // Assert slot 2 rates
        $this->assertDatabaseHas('slot_turf', [
            'turf_id' => $this->turf->id,
            'slot_id' => $slot2->id,
            'mon' => 750.00,
            'wed' => 750.00,
            'fri' => 750.00,
            'sat' => 1000.00,
            'sun' => 1000.00,
        ]);
    }
}
