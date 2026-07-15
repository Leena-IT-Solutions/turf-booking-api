<?php

namespace Tests\Feature;

use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SlotManagerTest extends TestCase
{
    use RefreshDatabase;

    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->category = SlotCategory::create([
            'name' => 'Morning',
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/saas/slots');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_slots(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/slots');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_slots(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/saas/slots');
        $response->assertOk();
    }

    public function test_saas_admin_can_create_slot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $component = Volt::test('saas.slot-manager')
            ->set('slot_category_id', $this->category->id)
            ->set('from_time', '06:00')
            ->set('to_time', '07:00')
            ->set('duration', 60)
            ->set('is_active', true)
            ->call('saveSlot');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('slots', [
            'slot_category_id' => $this->category->id,
            'duration' => 60,
            'is_active' => true,
        ]);
    }

    public function test_to_time_must_be_after_from_time(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $component = Volt::test('saas.slot-manager')
            ->set('slot_category_id', $this->category->id)
            ->set('from_time', '10:00')
            ->set('to_time', '09:00')
            ->set('duration', 60)
            ->call('saveSlot')
            ->assertHasErrors(['to_time' => 'after']);
    }

    public function test_saas_admin_can_update_slot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $slot = Slot::create([
            'slot_category_id' => $this->category->id,
            'from_time' => '08:00:00',
            'to_time' => '09:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-manager')
            ->call('editSlot', $slot->id)
            ->set('from_time', '08:30')
            ->set('to_time', '10:00')
            ->set('duration', 90)
            ->set('is_active', false)
            ->call('saveSlot');

        $component->assertHasNoErrors();

        $this->assertEquals('08:30', date('H:i', strtotime($slot->fresh()->from_time)));
        $this->assertEquals('10:00', date('H:i', strtotime($slot->fresh()->to_time)));
        $this->assertEquals(90, $slot->fresh()->duration);
        $this->assertFalse($slot->fresh()->is_active);
    }

    public function test_saas_admin_can_toggle_slot_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $slot = Slot::create([
            'slot_category_id' => $this->category->id,
            'from_time' => '11:00:00',
            'to_time' => '12:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-manager')
            ->call('toggleStatus', $slot->id);

        $this->assertFalse($slot->fresh()->is_active);
    }

    public function test_saas_admin_can_delete_slot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $slot = Slot::create([
            'slot_category_id' => $this->category->id,
            'from_time' => '13:00:00',
            'to_time' => '14:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-manager')
            ->call('deleteSlot', $slot->id);

        $this->assertDatabaseMissing('slots', ['id' => $slot->id]);
    }
}
