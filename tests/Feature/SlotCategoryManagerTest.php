<?php

namespace Tests\Feature;

use App\Models\SlotCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SlotCategoryManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/saas/slot-categories');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_slot_categories(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/slot-categories');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_slot_categories(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/saas/slot-categories');
        $response->assertOk();
    }

    public function test_saas_admin_can_create_slot_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $component = Volt::test('saas.slot-category-manager')
            ->set('name', 'Football')
            ->set('is_active', true)
            ->call('saveCategory');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('slot_categories', [
            'name' => 'Football',
            'is_active' => true,
        ]);
    }

    public function test_name_must_be_unique(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        SlotCategory::create([
            'name' => 'Badminton',
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-category-manager')
            ->set('name', 'Badminton')
            ->call('saveCategory')
            ->assertHasErrors(['name' => 'unique']);
    }

    public function test_saas_admin_can_update_slot_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $category = SlotCategory::create([
            'name' => 'Tennis',
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-category-manager')
            ->call('editCategory', $category->id)
            ->set('name', 'Table Tennis')
            ->set('is_active', false)
            ->call('saveCategory');

        $component->assertHasNoErrors();

        $this->assertEquals('Table Tennis', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_saas_admin_can_toggle_category_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $category = SlotCategory::create([
            'name' => 'Cricket',
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-category-manager')
            ->call('toggleStatus', $category->id);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_saas_admin_can_delete_slot_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $category = SlotCategory::create([
            'name' => 'Squash',
            'is_active' => true,
        ]);

        $component = Volt::test('saas.slot-category-manager')
            ->call('confirmDelete', $category->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete');

        $this->assertDatabaseMissing('slot_categories', ['id' => $category->id]);
    }
}
