<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/saas/users');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_user_manager(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/users');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_user_manager(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/saas/users');
        $response->assertOk();
    }

    public function test_saas_admin_can_create_user_with_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $component = Volt::test('saas.user-manager')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('mobile', '9876543210')
            ->set('password', 'secret123')
            ->set('selectedRoles', ['turf-admin', 'manager'])
            ->call('saveUser');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile' => '9876543210',
        ]);

        $createdUser = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('secret123', $createdUser->password));
        $this->assertTrue($createdUser->hasRole('turf-admin'));
        $this->assertTrue($createdUser->hasRole('manager'));
    }

    public function test_saas_admin_can_update_user_details_and_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $targetUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'mobile' => '1111111111',
        ]);
        $targetUser->assignRole('customer');

        $component = Volt::test('saas.user-manager')
            ->call('editUser', $targetUser->id)
            ->set('name', 'New Name')
            ->set('selectedRoles', ['saas-admin'])
            ->call('saveUser');

        $component->assertHasNoErrors();

        $this->assertEquals('New Name', $targetUser->fresh()->name);
        $this->assertTrue($targetUser->fresh()->hasRole('saas-admin'));
        $this->assertTrue($targetUser->fresh()->hasRole('customer'));
    }

    public function test_saas_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $component = Volt::test('saas.user-manager')
            ->call('deleteUser', $admin->id)
            ->assertSee('You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_saas_admin_can_delete_other_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $targetUser = User::factory()->create();
        $targetUser->assignRole('manager');

        $component = Volt::test('saas.user-manager')
            ->call('confirmDelete', $targetUser->id)
            ->assertSet('showDeleteConfirm', true)
            ->call('performDelete')
            ->assertSee('User deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }
}
