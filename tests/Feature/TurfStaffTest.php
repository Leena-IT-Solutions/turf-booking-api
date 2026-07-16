<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StaffMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_staff_page(): void
    {
        $this->get('/turf/staff')->assertRedirect('/login');
    }

    public function test_non_turf_admin_cannot_access_staff_page(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/turf/staff')->assertStatus(403);

        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)->get('/turf/staff')->assertStatus(403);
    }

    public function test_turf_admin_can_access_staff_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin)->get('/turf/staff')->assertOk();
    }

    public function test_turf_admin_can_search_user_by_email_or_mobile(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');
        $this->actingAs($admin);

        $targetUser = User::factory()->create([
            'email' => 'target@example.com',
            'mobile' => '9876543210',
        ]);

        // Search by email
        Volt::test('turf.staff-manager')
            ->set('searchQuery', 'target@example.com')
            ->call('search')
            ->assertSet('foundUser.id', $targetUser->id)
            ->assertHasNoErrors();

        // Search by mobile
        Volt::test('turf.staff-manager')
            ->set('searchQuery', '9876543210')
            ->call('search')
            ->assertSet('foundUser.id', $targetUser->id)
            ->assertHasNoErrors();

        // Search non-existing user
        Volt::test('turf.staff-manager')
            ->set('searchQuery', 'nonexistent@example.com')
            ->call('search')
            ->assertSet('foundUser', null)
            ->assertSet('messageType', 'error');
    }

    public function test_turf_admin_cannot_add_self_to_staff(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('turf-admin');
        $this->actingAs($admin);

        Volt::test('turf.staff-manager')
            ->set('searchQuery', 'admin@example.com')
            ->call('search')
            ->assertSet('foundUser', null)
            ->assertSet('messageType', 'error');
    }

    public function test_turf_admin_can_appoint_and_revoke_staff_members(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');
        $this->actingAs($admin);

        $targetUser = User::factory()->create([
            'email' => 'staffmember@example.com',
        ]);

        // Appoint as manager
        Volt::test('turf.staff-manager')
            ->set('searchQuery', 'staffmember@example.com')
            ->call('search')
            ->set('selectedRole', 'manager')
            ->call('addStaff')
            ->assertSet('messageType', 'success');

        $this->assertTrue($targetUser->hasRole('manager'));
        $this->assertDatabaseHas('staff_members', [
            'turf_admin_id' => $admin->id,
            'user_id' => $targetUser->id,
            'role' => 'manager',
        ]);

        $assignment = StaffMember::where('turf_admin_id', $admin->id)
            ->where('user_id', $targetUser->id)
            ->firstOrFail();

        // Revoke staff privileges
        Volt::test('turf.staff-manager')
            ->call('revokeStaff', $assignment->id)
            ->assertSet('messageType', 'success');

        $this->assertFalse($targetUser->fresh()->hasRole('manager'));
        $this->assertDatabaseMissing('staff_members', [
            'id' => $assignment->id,
        ]);
    }

    public function test_revoke_does_not_remove_global_role_if_assigned_to_another_admin(): void
    {
        $admin1 = User::factory()->create();
        $admin1->assignRole('turf-admin');

        $admin2 = User::factory()->create();
        $admin2->assignRole('turf-admin');

        $staffUser = User::factory()->create();

        // Appoint under Admin1
        StaffMember::create([
            'turf_admin_id' => $admin1->id,
            'user_id' => $staffUser->id,
            'role' => 'manager',
        ]);
        $staffUser->assignRole('manager');

        // Appoint under Admin2
        $assignment2 = StaffMember::create([
            'turf_admin_id' => $admin2->id,
            'user_id' => $staffUser->id,
            'role' => 'manager',
        ]);

        // Admin2 revokes staff User
        $this->actingAs($admin2);

        Volt::test('turf.staff-manager')
            ->call('revokeStaff', $assignment2->id);

        // Assert staffUser still has the manager role because of assignment under Admin1
        $this->assertTrue($staffUser->fresh()->hasRole('manager'));
        $this->assertDatabaseHas('staff_members', [
            'turf_admin_id' => $admin1->id,
            'user_id' => $staffUser->id,
            'role' => 'manager',
        ]);
    }
}
