<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_can_be_seeded_and_assigned(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'saas-admin']);
        $this->assertDatabaseHas('roles', ['name' => 'turf-admin']);
        $this->assertDatabaseHas('roles', ['name' => 'manager']);
        $this->assertDatabaseHas('roles', ['name' => 'customer']);

        $user = User::factory()->create();

        // Check initial state
        $this->assertFalse($user->hasRole('saas-admin'));

        // Assign one role
        $user->assignRole('saas-admin');
        $this->assertTrue($user->hasRole('saas-admin'));

        // Assign multiple roles
        $user->assignRole('turf-admin');
        $this->assertTrue($user->hasRole('saas-admin'));
        $this->assertTrue($user->hasRole('turf-admin'));
        $this->assertTrue($user->hasAnyRole(['saas-admin', 'manager']));
        $this->assertFalse($user->hasRole('manager'));

        // Retract role
        $user->retractRole('saas-admin');
        $this->assertFalse($user->hasRole('saas-admin'));
        $this->assertTrue($user->hasRole('turf-admin'));
    }

    public function test_api_login_returns_roles_list(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'password' => bcrypt('password')
        ]);
        $user->assignRole('saas-admin');
        $user->assignRole('manager');

        $response = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'password'
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.roles.0', 'saas-admin')
            ->assertJsonPath('user.roles.1', 'manager');
    }
}
