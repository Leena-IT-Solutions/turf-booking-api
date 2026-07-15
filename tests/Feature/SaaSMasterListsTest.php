<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Equipment;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaaSMasterListsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $turfAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('saas-admin');

        $this->turfAdmin = User::factory()->create();
        $this->turfAdmin->assignRole('turf-admin');
    }

    public function test_unauthorized_users_cannot_access_global_master_lists(): void
    {
        $this->actingAs($this->turfAdmin);

        $this->get('/saas/facilities')->assertStatus(403);
        $this->get('/saas/equipments')->assertStatus(403);
        $this->get('/saas/sports')->assertStatus(403);
    }

    public function test_saas_admin_can_access_global_master_lists(): void
    {
        $this->actingAs($this->admin);

        $this->get('/saas/facilities')->assertOk();
        $this->get('/saas/equipments')->assertOk();
        $this->get('/saas/sports')->assertOk();
    }

    public function test_saas_admin_can_manage_global_facilities(): void
    {
        $this->actingAs($this->admin);

        // 1. Initial State
        Livewire::test('saas.facilities-manager')
            ->assertSee('No Facilities Options Found');

        // 2. Create Facility
        Livewire::test('saas.facilities-manager')
            ->call('openCreateModal')
            ->set('name', 'Locker Room')
            ->set('is_active', true)
            ->call('saveFacility')
            ->assertHasNoErrors()
            ->assertSee('Facility created successfully.');

        $this->assertDatabaseHas('facilities', [
            'name' => 'Locker Room',
            'is_active' => true,
        ]);

        $facility = Facility::first();

        // 3. Edit Facility
        Livewire::test('saas.facilities-manager')
            ->call('openEditModal', $facility->id)
            ->assertSet('name', 'Locker Room')
            ->set('name', 'Luxury Showers')
            ->call('saveFacility')
            ->assertHasNoErrors()
            ->assertSee('Facility updated successfully.');

        $this->assertEquals('Luxury Showers', $facility->fresh()->name);

        // 4. Toggle Active Status
        Livewire::test('saas.facilities-manager')
            ->call('toggleActive', $facility->id);

        $this->assertFalse($facility->fresh()->is_active);

        // 5. Delete Facility
        Livewire::test('saas.facilities-manager')
            ->call('confirmDelete', $facility->id)
            ->assertSet('deletingId', $facility->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete')
            ->assertSee('Facility deleted successfully.');

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }

    public function test_saas_admin_can_manage_global_equipments(): void
    {
        $this->actingAs($this->admin);

        // 1. Initial State
        Livewire::test('saas.equipments-manager')
            ->assertSee('No Equipments Options Found');

        // 2. Create Equipment
        Livewire::test('saas.equipments-manager')
            ->call('openCreateModal')
            ->set('name', 'Wilson Tennis Balls')
            ->set('is_active', true)
            ->call('saveEquipment')
            ->assertHasNoErrors()
            ->assertSee('Equipment created successfully.');

        $this->assertDatabaseHas('equipments', [
            'name' => 'Wilson Tennis Balls',
            'is_active' => true,
        ]);

        $equipment = Equipment::first();

        // 3. Edit Equipment
        Livewire::test('saas.equipments-manager')
            ->call('openEditModal', $equipment->id)
            ->set('name', 'Wilson Rackets')
            ->call('saveEquipment')
            ->assertHasNoErrors()
            ->assertSee('Equipment updated successfully.');

        $this->assertEquals('Wilson Rackets', $equipment->fresh()->name);

        // 4. Toggle Active Status
        Livewire::test('saas.equipments-manager')
            ->call('toggleActive', $equipment->id);

        $this->assertFalse($equipment->fresh()->is_active);

        // 5. Delete Equipment
        Livewire::test('saas.equipments-manager')
            ->call('confirmDelete', $equipment->id)
            ->call('performDelete')
            ->assertSee('Equipment deleted successfully.');

        $this->assertDatabaseMissing('equipments', ['id' => $equipment->id]);
    }

    public function test_saas_admin_can_manage_global_sports(): void
    {
        $this->actingAs($this->admin);

        // 1. Initial State
        Livewire::test('saas.sports-manager')
            ->assertSee('No Sports Options Found');

        // 2. Create Sport
        Livewire::test('saas.sports-manager')
            ->call('openCreateModal')
            ->set('name', 'Box Cricket')
            ->set('is_active', true)
            ->call('saveSport')
            ->assertHasNoErrors()
            ->assertSee('Sport created successfully.');

        $this->assertDatabaseHas('sports', [
            'name' => 'Box Cricket',
            'is_active' => true,
        ]);

        $sport = Sport::first();

        // 3. Edit Sport
        Livewire::test('saas.sports-manager')
            ->call('openEditModal', $sport->id)
            ->set('name', 'Indoor Cricket')
            ->call('saveSport')
            ->assertHasNoErrors()
            ->assertSee('Sport updated successfully.');

        $this->assertEquals('Indoor Cricket', $sport->fresh()->name);

        // 4. Toggle Active Status
        Livewire::test('saas.sports-manager')
            ->call('toggleActive', $sport->id);

        $this->assertFalse($sport->fresh()->is_active);

        // 5. Delete Sport
        Livewire::test('saas.sports-manager')
            ->call('confirmDelete', $sport->id)
            ->call('performDelete')
            ->assertSee('Sport deleted successfully.');

        $this->assertDatabaseMissing('sports', ['id' => $sport->id]);
    }
}
