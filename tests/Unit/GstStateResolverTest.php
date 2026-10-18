<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use App\Services\GstStateResolver;
use App\Support\IndianStates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GstStateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_indian_states_lookup_and_validation(): void
    {
        $this->assertSame('Karnataka', IndianStates::name('29'));
        $this->assertSame('Maharashtra', IndianStates::name('27'));
        $this->assertSame('Jammu and Kashmir', IndianStates::name('1'));
        $this->assertNull(IndianStates::name('99'));
        $this->assertNull(IndianStates::name(null));

        $this->assertTrue(IndianStates::isValid('29'));
        $this->assertTrue(IndianStates::isValid('01'));
        $this->assertTrue(IndianStates::isValid('1'));
        $this->assertFalse(IndianStates::isValid('99'));
        $this->assertFalse(IndianStates::isValid(''));
        $this->assertFalse(IndianStates::isValid(null));
    }

    public function test_state_from_gstin(): void
    {
        $this->assertSame('29', GstStateResolver::stateFromGstin('29ABCDE1234F1Z5'));
        $this->assertSame('27', GstStateResolver::stateFromGstin('27AAAAA0000A1Z5'));
        $this->assertNull(GstStateResolver::stateFromGstin('99ABCDE1234F1Z5'));
        $this->assertNull(GstStateResolver::stateFromGstin('garbage'));
        $this->assertNull(GstStateResolver::stateFromGstin(null));
        $this->assertNull(GstStateResolver::stateFromGstin(''));
    }

    public function test_turf_state_code_resolution(): void
    {
        $user = User::factory()->create();
        $location = Location::create([
            'user_id' => $user->id,
            'name' => 'Bangalore Arena',
            'address' => 'MG Road',
        ]);
        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Green Pitch',
            'type' => 'Football',
        ]);

        // No TurfSetting exists yet
        $this->assertNull(GstStateResolver::turfStateCode($turf));

        // TurfSetting exists but neither state_code nor valid GSTIN exists
        $setting = TurfSetting::create([
            'turf_id' => $turf->id,
            'state_code' => null,
            'gst_number' => null,
        ]);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertNull(GstStateResolver::turfStateCode($turf));

        // GSTIN prefix when state_code is empty
        $setting->update([
            'state_code' => null,
            'gst_number' => '29ABCDE1234F1Z5',
        ]);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertSame('29', GstStateResolver::turfStateCode($turf));

        // State code when set directly
        $setting->update([
            'state_code' => '29',
            'gst_number' => null,
        ]);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertSame('29', GstStateResolver::turfStateCode($turf));

        // State code formatted as 2 digits if given as single digit
        $setting->update([
            'state_code' => '7',
            'gst_number' => null,
        ]);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertSame('07', GstStateResolver::turfStateCode($turf));
    }

    public function test_saas_state_code_resolution(): void
    {
        $this->assertSame('27', GstStateResolver::saasStateCode(null));

        $saas = SaasSetting::create([
            'state_code' => '29',
            'gst_number' => null,
        ]);
        $this->assertSame('29', GstStateResolver::saasStateCode($saas));

        $saas->update([
            'state_code' => null,
            'gst_number' => '24ABCDE1234F1Z5',
        ]);
        $this->assertSame('24', GstStateResolver::saasStateCode($saas));

        $saas->update([
            'state_code' => null,
            'gst_number' => null,
        ]);
        $this->assertSame('27', GstStateResolver::saasStateCode($saas));
    }

    public function test_is_intra_state_determination(): void
    {
        $user = User::factory()->create();
        $location = Location::create([
            'user_id' => $user->id,
            'name' => 'City Arena',
            'address' => 'Sample Road',
        ]);
        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Main Turf',
            'type' => 'Cricket',
        ]);
        $saas = SaasSetting::create([
            'state_code' => '27',
        ]);

        // Turf with no state: logs warning and defaults to intra-state (true)
        Log::shouldReceive('warning')->once();
        $this->assertTrue(GstStateResolver::isIntraState($turf, $saas));

        // Turf 27 / SaaS 27 -> true
        $setting = TurfSetting::create([
            'turf_id' => $turf->id,
            'state_code' => '27',
        ]);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertTrue(GstStateResolver::isIntraState($turf, $saas));

        // Turf 29 / SaaS 27 -> false
        $setting->update(['state_code' => '29']);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertFalse(GstStateResolver::isIntraState($turf, $saas));

        // Turf with no state_code but a GSTIN starting 29 -> false (regression test for bug 1)
        $setting->update([
            'state_code' => null,
            'gst_number' => '29ABCDE1234F1Z5',
        ]);
        $turf->unsetRelation('turfSetting');
        $turf->unsetRelation('setting');
        $this->assertFalse(GstStateResolver::isIntraState($turf, $saas));
    }
}
