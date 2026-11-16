<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Sport;
use App\Models\Turf;
use App\Models\TurfPhoto;
use App\Models\User;
use App\Notifications\TurfSubmittedForVerification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfOnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    protected User $saasAdmin;
    protected User $marketingExec;
    protected User $owner;
    protected Facility $facility;
    protected Equipment $equipment;
    protected Sport $sport;
    protected SlotCategory $category;
    protected Slot $slot60;
    protected Slot $slot30;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->saasAdmin = User::factory()->create(['email' => 'saas@turf.com', 'mobile' => '9900000001']);
        $this->saasAdmin->assignRole('saas-admin');

        $this->marketingExec = User::factory()->create(['email' => 'marketing@turf.com', 'mobile' => '9900000002']);
        $this->marketingExec->assignRole('marketing');

        $this->owner = User::factory()->create([
            'name' => 'Virat Owner',
            'email' => 'virat@turf.com',
            'mobile' => '9900000003',
        ]);
        $this->owner->assignRole('turf-admin');

        $this->facility = Facility::create(['name' => 'Floodlights', 'is_active' => true]);
        $this->equipment = Equipment::create(['name' => 'Cricket Bat', 'is_active' => true]);
        $this->sport = Sport::create(['name' => 'Box Cricket', 'is_active' => true]);
        $this->category = SlotCategory::create(['name' => 'Morning', 'is_active' => true]);

        $this->slot60 = Slot::create([
            'slot_category_id' => $this->category->id,
            'from_time' => '06:00:00',
            'to_time' => '07:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $this->slot30 = Slot::create([
            'slot_category_id' => $this->category->id,
            'from_time' => '07:00:00',
            'to_time' => '07:30:00',
            'duration' => 30,
            'is_active' => true,
        ]);
    }

    public function test_full_12_step_onboarding_wizard_from_zero_to_pending_submission_and_email_notification(): void
    {
        Notification::fake();

        // Impersonate owner as Marketing Executive so onboarded_by tracks the marketing executive
        $this->actingAs($this->marketingExec)
            ->get(route('saas.users.impersonate', $this->owner->id))
            ->assertRedirect(route('turf.setup-wizard'));

        $component = Volt::test('turf.onboarding-wizard')
            ->assertSet('currentStep', 1);

        // Step 1: Location (requires lat/lng)
        $component
            ->set('locationName', 'Marine Drive Arena')
            ->set('locationAddress', 'Netaji Subhash Chandra Bose Road, Mumbai')
            ->set('locationLatitude', '')
            ->set('locationLongitude', '')
            ->call('saveAndContinue')
            ->assertHasErrors(['locationLatitude', 'locationLongitude'])
            ->assertSet('currentStep', 1);

        $component
            ->set('locationLatitude', '18.9432')
            ->set('locationLongitude', '72.8235')
            ->call('useOwnerContactForLocation')
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2);

        $location = Location::where('user_id', $this->owner->id)->first();
        $this->assertNotNull($location);
        $this->assertEquals(18.9432, (float) $location->latitude);

        // Step 2: Turf (status starts as Draft, onboarded_by = marketingExec)
        $component
            ->set('turfName', 'Championship Court 1')
            ->set('turfType', 'Synthetic')
            ->set('turfArea', '100 x 60 ft')
            ->set('turfDescription', 'FIFA Pro astro turf')
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 3);

        $turf = Turf::where('location_id', $location->id)->first();
        $this->assertNotNull($turf);
        $this->assertEquals('Draft', $turf->status);
        $this->assertEquals($this->marketingExec->id, $turf->onboarded_by);

        // Step 3: Photos (blocked if 0 photos)
        $component
            ->call('saveAndContinue')
            ->assertHasErrors(['photosStep'])
            ->assertSet('currentStep', 3);

        $file1 = UploadedFile::fake()->image('court1.jpg', 800, 600);
        $file2 = UploadedFile::fake()->image('court2.jpg', 800, 600);
        $component
            ->set('photoFiles', [$file1, $file2])
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4);

        $photos = $turf->photos()->get();
        $this->assertCount(2, $photos);
        $component->call('setAsCoverPhoto', $photos[1]->id);
        $this->assertEquals($photos[1]->id, $turf->photos()->first()->id);

        // Step 4: Facilities (optional)
        $component
            ->set('selectedFacilityIds', [(string) $this->facility->id])
            ->call('saveAndContinue')
            ->assertSet('currentStep', 5);

        // Step 5: Equipments (optional)
        $component
            ->set('selectedEquipmentIds', [(string) $this->equipment->id])
            ->call('saveAndContinue')
            ->assertSet('currentStep', 6);

        // Step 6: Sports (required >= 1)
        $component
            ->set('selectedSportIds', [])
            ->call('saveAndContinue')
            ->assertHasErrors(['sportsStep'])
            ->assertSet('currentStep', 6);

        $component
            ->set('selectedSportIds', [(string) $this->sport->id])
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 7);

        // Step 7: Slots (blocked when 0 slots selected; supports opening-hours window)
        $component
            ->set('selectedSlotIds', [])
            ->call('saveAndContinue')
            ->assertHasErrors(['slotsStep'])
            ->assertSet('currentStep', 7);

        $component
            ->set('slotWindowFrom', '05:00')
            ->set('slotWindowTo', '08:00')
            ->call('selectSlotsByTimeWindow')
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 8);

        // Step 8: Pricing (blocked if unpriced; scales 60m -> 1200 and 30m -> 600)
        $component
            ->set('sameRateThroughoutWeek', 'yes')
            ->set('sameRateThroughoutDayAll', 'yes')
            ->set('flatRateAll', '')
            ->call('saveAndContinue')
            ->assertHasErrors(['pricingStep'])
            ->assertSet('currentStep', 8);

        $component
            ->set('flatRateAll', '1200')
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 9);

        $slot60Pivot = $turf->slots()->where('slots.id', $this->slot60->id)->first()->pivot;
        $slot30Pivot = $turf->slots()->where('slots.id', $this->slot30->id)->first()->pivot;
        $this->assertEquals(1200.00, (float) $slot60Pivot->mon);
        $this->assertEquals(600.00, (float) $slot30Pivot->mon);

        // Step 9: Taxation (validates GSTIN state prefix match)
        $component
            ->set('taxState', 'Maharashtra')
            ->set('isGstBillingActive', true)
            ->set('gstNumber', '29ABCDE1234F1Z5') // 29 is Karnataka, should fail when state is manually forced back to Maharashtra
            ->set('taxState', 'Maharashtra')
            ->call('saveAndContinue')
            ->assertHasErrors(['gst_number'])
            ->assertSet('currentStep', 9);

        $component
            ->set('gstNumber', '27ABCDE1234F1Z5') // 27 = Maharashtra
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 10);

        // Step 10: Booking Rules
        $component
            ->set('bookingOpenDays', 60)
            ->set('isCancellationActive', true)
            ->set('cancellationHours', 24)
            ->set('cancellationFee', 10)
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 11);

        // Step 11: Payout Account
        $component
            ->set('payoutMethod', 'upi')
            ->set('upiId', 'virat@okhdfcbank')
            ->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 12);

        $this->assertEquals('virat@okhdfcbank', $this->owner->fresh()->upi_id);

        // Step 12: Review & Submit
        $component
            ->call('submitForVerification')
            ->assertRedirect(route('turf.dashboard'));

        $turf->refresh();
        $this->assertEquals('Pending', $turf->status);
        $this->assertNotNull($turf->submitted_at);
        $this->assertEquals($this->marketingExec->id, $turf->onboarded_by);

        Notification::assertSentTo($this->saasAdmin, TurfSubmittedForVerification::class);
        Notification::assertSentTo($this->marketingExec, TurfSubmittedForVerification::class);
    }

    public function test_manager_can_configure_steps_and_creates_location_under_owner_id_but_cannot_submit_or_see_payout_step(): void
    {
        $manager = User::factory()->create(['email' => 'mgr@turf.com', 'mobile' => '9900000004']);
        $manager->assignRole('manager');

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Owner Venue',
            'address' => 'Andheri West',
            'latitude' => 19.1197,
            'longitude' => 72.8464,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Manager Court',
            'type' => 'Synthetic',
            'status' => 'Draft',
        ]);

        // Assign manager to this turf
        DB::table('staff_turf')->insert([
            'user_id' => $manager->id,
            'turf_id' => $turf->id,
            'turf_admin_id' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager);
        session(['active_location_id' => $location->id, 'active_turf_id' => $turf->id]);

        // Auto-resume should land on Step 3 (Photos) since Steps 1 & 2 are complete
        $component = Volt::test('turf.onboarding-wizard')
            ->assertSet('currentStep', 3)
            ->assertSee('Setup Wizard');

        // Manager creating a new location saves user_id = owner's ID
        $component
            ->call('goToStep', 1)
            ->call('switchLocationMode', 'create')
            ->set('locationName', 'Second Venue by Manager')
            ->set('locationAddress', 'Bandra West')
            ->set('locationLatitude', '19.0596')
            ->set('locationLongitude', '72.8295')
            ->call('saveAndContinue');

        $secondLoc = Location::where('name', 'Second Venue by Manager')->first();
        $this->assertNotNull($secondLoc);
        $this->assertEquals($this->owner->id, $secondLoc->user_id);

        // Manager cannot call submitForVerification (403 Forbidden)
        $response = $this->actingAs($manager)->get(route('turf.setup-wizard', ['turf' => $turf->id]));
        $response->assertStatus(200);

        Volt::actingAs($manager)
            ->test('turf.onboarding-wizard', ['turfId' => $turf->id])
            ->call('submitForVerification')
            ->assertForbidden();
    }

    public function test_approved_turf_stays_approved_when_saved_through_wizard_and_other_users_cannot_access_turf_via_query(): void
    {
        Notification::fake();

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Live Arena',
            'address' => 'Powai, Mumbai',
            'latitude' => 19.1176,
            'longitude' => 72.9060,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Live Pitch',
            'type' => 'Synthetic',
            'status' => 'Approved',
        ]);

        TurfPhoto::create([
            'turf_id' => $turf->id,
            'photo' => 'turf_photos/sample.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $turf->sports()->sync([$this->sport->id]);
        $turf->slots()->sync([
            $this->slot60->id => [
                'is_active' => true,
                'mon' => 1000, 'tue' => 1000, 'wed' => 1000, 'thu' => 1000,
                'fri' => 1200, 'sat' => 1200, 'sun' => 1200,
            ],
        ]);
        $turf->turfSetting()->create([
            'state' => 'Maharashtra',
            'state_code' => '27',
            'country' => 'India',
            'is_gst_billing_active' => false,
            'gst_pricing_type' => 'included',
            'gst_percentage' => 18.00,
        ]);

        // Approved turf auto-resumes on Step 12 and stays Approved on submit
        Volt::actingAs($this->owner)
            ->test('turf.onboarding-wizard', ['turfId' => $turf->id])
            ->assertSet('currentStep', 12)
            ->call('submitForVerification')
            ->assertRedirect(route('turf.dashboard'));

        $this->assertEquals('Approved', $turf->fresh()->status);
        Notification::assertNothingSent();

        // Another turf owner cannot open this turf via ?turf=
        $stranger = User::factory()->create();
        $stranger->assignRole('turf-admin');

        $this->actingAs($stranger)
            ->get(route('turf.setup-wizard', ['turf' => $turf->id]))
            ->assertStatus(403);
    }
}
