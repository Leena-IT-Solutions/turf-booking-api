<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfVerificationGstTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $owner;
    protected Location $location;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('saas-admin');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('turf-admin');

        $this->location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Bangalore Hub',
            'address' => 'Indiranagar 100ft Rd',
        ]);

        $this->turf = Turf::create([
            'location_id' => $this->location->id,
            'name' => 'Kanteerava Arena',
            'type' => 'Football',
            'status' => 'Pending',
        ]);
    }

    public function test_approving_without_state_is_refused_and_status_unchanged(): void
    {
        $this->actingAs($this->admin);

        Volt::test('saas.turf-verification')
            ->call('openVerifyModal', $this->turf->id)
            ->set('stateCode', '')
            ->call('updateStatus', $this->turf->id, 'Approved')
            ->assertHasErrors(['stateCode'])
            ->assertSee("Set the turf's state before approving. It decides how GST is charged.");

        $this->assertSame('Pending', $this->turf->fresh()->status);
    }

    public function test_approving_with_valid_state_updates_turf_setting_with_state_code(): void
    {
        $this->actingAs($this->admin);

        Volt::test('saas.turf-verification')
            ->call('openVerifyModal', $this->turf->id)
            ->set('stateCode', '29')
            ->call('updateStatus', $this->turf->id, 'Approved')
            ->assertHasNoErrors();

        $this->assertSame('Approved', $this->turf->fresh()->status);

        $this->assertDatabaseHas('turf_settings', [
            'turf_id' => $this->turf->id,
            'state' => 'Karnataka',
            'state_code' => '29',
        ]);
    }

    public function test_approving_with_gstin_state_mismatch_is_refused(): void
    {
        $this->actingAs($this->admin);

        // Turf owner registered a Maharashtra GSTIN
        TurfSetting::create([
            'turf_id' => $this->turf->id,
            'gst_number' => '27ABCDE1234F1Z5',
        ]);

        Volt::test('saas.turf-verification')
            ->call('openVerifyModal', $this->turf->id)
            ->set('stateCode', '29') // Admin tries to set Karnataka
            ->call('updateStatus', $this->turf->id, 'Approved')
            ->assertHasErrors(['stateCode'])
            ->assertSee('This GSTIN is registered in Maharashtra, but the selected state is Karnataka.');

        $this->assertSame('Pending', $this->turf->fresh()->status);
    }

    public function test_dashboard_and_taxation_banner_shows_when_state_missing_and_hides_when_set(): void
    {
        $this->actingAs($this->owner);
        session(['active_turf_id' => $this->turf->id]);

        // State is missing: banner visible on dashboard and taxation
        Volt::test('turf.dashboard-manager')
            ->assertSee("Your turf's state is not set. Set it on the Taxation page so GST on your bookings and commission is charged correctly.");

        Volt::test('turf.taxation-manager')
            ->assertSee("Your turf's state is not set. Set it on the Taxation page so GST on your bookings and commission is charged correctly.");

        // Set state
        TurfSetting::updateOrCreate(
            ['turf_id' => $this->turf->id],
            [
                'state' => 'Karnataka',
                'state_code' => '29',
            ]
        );

        Volt::test('turf.dashboard-manager')
            ->assertDontSee("Your turf's state is not set. Set it on the Taxation page so GST on your bookings and commission is charged correctly.");

        Volt::test('turf.taxation-manager')
            ->assertDontSee("Your turf's state is not set. Set it on the Taxation page so GST on your bookings and commission is charged correctly.");
    }

    public function test_audit_command_lists_turfs_missing_state(): void
    {
        // $this->turf currently has no state
        $this->artisan('gst:turfs-missing-state')
            ->expectsOutputToContain('Kanteerava Arena')
            ->expectsOutputToContain('Found 1 turf(s) missing GST state.')
            ->assertExitCode(0);

        // Add state
        TurfSetting::create([
            'turf_id' => $this->turf->id,
            'state' => 'Karnataka',
            'state_code' => '29',
        ]);

        $this->artisan('gst:turfs-missing-state')
            ->expectsOutput('All turfs have a valid GST state set.')
            ->assertExitCode(0);
    }
}
