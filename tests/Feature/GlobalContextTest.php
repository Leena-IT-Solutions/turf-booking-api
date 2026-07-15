<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GlobalContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_global_context_initializes_properly(): void
    {
        $user = User::factory()->create();
        $user->assignRole('turf-admin');
        $this->actingAs($user);

        $location1 = Location::create([
            'user_id' => $user->id,
            'name' => 'Bandra Court',
            'address' => 'Mumbai',
        ]);

        $location2 = Location::create([
            'user_id' => $user->id,
            'name' => 'Andheri Court',
            'address' => 'Mumbai',
        ]);

        $turf = Turf::create([
            'location_id' => $location1->id,
            'name' => 'Pitch A',
            'type' => 'Synthetic',
        ]);

        // Component mount sets active location and turf (alphabetical sort order means Andheri Court is first)
        Volt::test('layout.global-context-selector')
            ->assertSet('selectedLocationId', $location2->id)
            ->assertSet('selectedTurfId', null);

        // Select Bandra Court
        Volt::test('layout.global-context-selector')
            ->set('selectedLocationId', $location1->id)
            ->assertSet('selectedTurfId', $turf->id)
            ->assertSessionHas('active_location_id', $location1->id)
            ->assertSessionHas('active_turf_id', $turf->id)
            ->assertDispatched('global-context-updated');
    }
}
