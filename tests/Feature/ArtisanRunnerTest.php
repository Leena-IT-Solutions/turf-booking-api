<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_run_artisan_commands(): void
    {
        $response = $this->postJson('/artisan-run', ['command' => 'migrate']);
        $response->assertStatus(302); // Redirects to login
    }

    public function test_non_admin_cannot_run_artisan_commands(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->postJson('/artisan-run', ['command' => 'migrate']);
        $response->assertStatus(403);
    }

    public function test_admin_can_run_artisan_migrate_command(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $response = $this->actingAs($admin)->postJson('/artisan-run', ['command' => 'migrate']);
        $response->assertOk()
            ->assertJson([
                'success' => true
            ]);
    }

    public function test_invalid_command_returns_bad_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $response = $this->actingAs($admin)->postJson('/artisan-run', ['command' => 'invalid-command']);
        $response->assertStatus(400);
    }
}
