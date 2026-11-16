<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Sport;
use App\Models\Turf;
use App\Models\TurfPhoto;
use App\Models\TurfSetting;
use App\Models\User;
use App\Notifications\TurfSubmittedForVerification;
use App\Services\TurfOnboardingProgress;
use App\Services\TurfPricingService;
use App\Services\TurfTaxationService;
use App\Support\IndianStates;
use App\Support\PayoutDestination;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    #[Url(as: 'turf')]
    public ?int $turfId = null;

    #[Url(as: 'step')]
    public int $currentStep = 1;

    // Step 1: Location state
    public string $locationMode = 'create'; // 'existing' or 'create'
    public ?int $locationId = null;
    public string $locationName = '';
    public string $locationAddress = '';
    public string $locationLatitude = '';
    public string $locationLongitude = '';
    public string $locationContactNumber = '';
    public string $locationEmail = '';

    // Step 2: Turf state
    public string $turfMode = 'create'; // 'existing' or 'create'
    public string $turfName = '';
    public string $turfType = 'Synthetic';
    public string $turfArea = '';
    public string $turfDescription = '';
    public bool $turfIsActive = true;

    // Step 3: Photos state
    public array $photoFiles = [];

    // Steps 4, 5, 6: Catalog selections
    public array $selectedFacilityIds = [];
    public array $selectedEquipmentIds = [];
    public array $selectedSportIds = [];

    // Step 7: Slots state
    public array $selectedSlotIds = [];
    public string $slotWindowFrom = '06:00';
    public string $slotWindowTo = '23:00';

    // Step 8: Pricing state
    public string $sameRateThroughoutWeek = 'yes';
    public string $sameRateThroughoutDayAll = 'yes';
    public $flatRateAll = '';
    public array $timeRangesAll = [['from' => '00:00', 'to' => '23:59', 'rate' => '']];
    public array $dayGroups = [
        [
            'days' => ['mon', 'tue', 'wed', 'thu'],
            'sameRateThroughoutDay' => 'yes',
            'flatRate' => '',
            'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']],
        ],
        [
            'days' => ['fri', 'sat', 'sun'],
            'sameRateThroughoutDay' => 'yes',
            'flatRate' => '',
            'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']],
        ],
    ];

    // Step 9: Taxation state
    public string $taxCompanyName = '';
    public string $taxCompanyEmail = '';
    public string $taxCompanyPhone = '';
    public string $taxAddress = '';
    public string $taxCity = '';
    public string $taxState = '';
    public ?string $taxStateCode = null;
    public string $taxPincode = '';
    public bool $isGstBillingActive = false;
    public string $gstPricingType = 'included';
    public float $gstPercentage = 18.00;
    public string $gstNumber = '';

    // Step 10: Booking & Payment Rules state
    public bool $isOnlinePaymentActive = true;
    public bool $isPartPaymentActive = false;
    public bool $isPayAtLocationActive = false;
    public string $partPaymentType = 'percentage';
    public $partPaymentValue = 50;
    public bool $isBookingOpen = true;
    public int $bookingOpenDays = 90;
    public bool $isManagerBookingActive = true;
    public bool $isCancellationActive = false;
    public int $cancellationHours = 48;
    public $cancellationFee = 0;
    public bool $isRescheduleActive = true;
    public int $maxReschedules = 2;

    // Step 11: Payout Account state
    public string $payoutMethod = 'bank';
    public string $bankAccountName = '';
    public string $bankAccountNumber = '';
    public string $bankIfsc = '';
    public string $upiId = '';

    public function mount(): void
    {
        $requestedStep = request()->query('step') ? (int) request()->query('step') : null;
        $requestedTurfId = request()->query('turf') ? (int) request()->query('turf') : null;

        if ($requestedTurfId) {
            $turf = Turf::manageable()->find($requestedTurfId);
            if (!$turf) {
                abort(403, 'You do not have permission to configure this turf.');
            }
            $this->turfId = $turf->id;
            $this->locationId = $turf->location_id;
            session([
                'active_location_id' => $turf->location_id,
                'active_turf_id' => $turf->id,
            ]);
        } else {
            $activeTurfId = session('active_turf_id');
            if ($activeTurfId) {
                $turf = Turf::manageable()->find($activeTurfId);
                if ($turf) {
                    $this->turfId = $turf->id;
                    $this->locationId = $turf->location_id;
                }
            }

            if (!$this->turfId) {
                $latestTurf = Turf::manageable()->latest('id')->first();
                if ($latestTurf) {
                    $this->turfId = $latestTurf->id;
                    $this->locationId = $latestTurf->location_id;
                    session([
                        'active_location_id' => $latestTurf->location_id,
                        'active_turf_id' => $latestTurf->id,
                    ]);
                } elseif (session('active_location_id')) {
                    $loc = Location::manageable()->find(session('active_location_id'));
                    $this->locationId = $loc?->id;
                } else {
                    $firstLoc = Location::manageable()->first();
                    $this->locationId = $firstLoc?->id;
                }
            }
        }

        $this->hydrateFromModels();

        $progress = $this->computeProgress();
        if ($requestedStep && isset($progress['steps'][$requestedStep]) && !$progress['steps'][$requestedStep]['hidden'] && $progress['steps'][$requestedStep]['unlocked']) {
            $this->currentStep = $requestedStep;
        } else {
            $this->currentStep = $progress['first_incomplete_step'];
        }
    }

    #[On('global-context-updated')]
    public function handleGlobalContextChanged(): void
    {
        $sessionTurfId = session('active_turf_id');
        $sessionLocId = session('active_location_id');

        if ($sessionTurfId) {
            $turf = Turf::manageable()->find($sessionTurfId);
            if ($turf) {
                $this->turfId = $turf->id;
                $this->locationId = $turf->location_id;
            }
        } elseif ($sessionLocId) {
            $loc = Location::manageable()->find($sessionLocId);
            $this->locationId = $loc?->id;
            $this->turfId = null;
        }

        $this->hydrateFromModels();
    }

    public function startNewTurfSetup(): void
    {
        $this->turfId = null;
        $this->turfMode = 'create';
        $this->turfName = '';
        $this->turfType = 'Synthetic';
        $this->turfArea = '';
        $this->turfDescription = '';
        $this->turfIsActive = true;
        $this->selectedFacilityIds = [];
        $this->selectedEquipmentIds = [];
        $this->selectedSportIds = [];
        $this->selectedSlotIds = [];
        session()->forget('active_turf_id');

        if ($this->locationId) {
            $this->locationMode = 'existing';
            $this->currentStep = 2;
        } else {
            $this->locationMode = 'create';
            $this->currentStep = 1;
        }
    }

    public function hydrateFromModels(): void
    {
        $location = $this->locationId ? Location::manageable()->find($this->locationId) : null;
        $turf = $this->turfId ? Turf::manageable()->find($this->turfId) : null;

        if ($turf && !$location) {
            $location = $turf->location;
            $this->locationId = $location?->id;
        }

        if ($location) {
            $this->locationMode = 'existing';
            $this->locationName = (string) $location->name;
            $this->locationAddress = (string) $location->address;
            $this->locationLatitude = $location->latitude !== null ? (string) $location->latitude : '';
            $this->locationLongitude = $location->longitude !== null ? (string) $location->longitude : '';
            $this->locationContactNumber = (string) ($location->contact_number ?? '');
            $this->locationEmail = (string) ($location->email ?? '');
        } else {
            $this->locationMode = 'create';
        }

        if ($turf) {
            $this->turfMode = 'existing';
            $this->turfName = (string) $turf->name;
            $this->turfType = (string) ($turf->type ?: 'Synthetic');
            $this->turfArea = (string) ($turf->area ?? '');
            $this->turfDescription = (string) ($turf->description ?? '');
            $this->turfIsActive = (bool) $turf->is_active;

            $this->selectedFacilityIds = $turf->facilities()->pluck('facilities.id')->map(fn ($id) => (string) $id)->toArray();
            $this->selectedEquipmentIds = $turf->turfEquipments()->pluck('equipments.id')->map(fn ($id) => (string) $id)->toArray();
            $this->selectedSportIds = $turf->sports()->pluck('sports.id')->map(fn ($id) => (string) $id)->toArray();
            $this->selectedSlotIds = $turf->slots()->wherePivot('is_active', true)->pluck('slots.id')->map(fn ($id) => (string) $id)->toArray();

            if ($turf->pricing_wizard_data) {
                $pData = $turf->pricing_wizard_data;
                $this->sameRateThroughoutWeek = (string) ($pData['sameRateThroughoutWeek'] ?? 'yes');
                $this->sameRateThroughoutDayAll = (string) ($pData['sameRateThroughoutDayAll'] ?? 'yes');
                $this->flatRateAll = $pData['flatRateAll'] ?? '';
                $this->timeRangesAll = $pData['timeRangesAll'] ?? [['from' => '00:00', 'to' => '23:59', 'rate' => '']];
                $this->dayGroups = $pData['dayGroups'] ?? $this->dayGroups;
            }

            $setting = $turf->turfSetting;
            if ($setting) {
                $this->taxCompanyName = (string) ($setting->company_name ?? '');
                $this->taxCompanyEmail = (string) ($setting->company_email ?? '');
                $this->taxCompanyPhone = (string) ($setting->company_phone ?? '');
                $this->taxAddress = (string) ($setting->address ?? '');
                $this->taxCity = (string) ($setting->city ?? '');
                $this->taxState = (string) ($setting->state ?? '');
                $this->taxStateCode = $setting->state_code ?: null;
                $this->taxPincode = (string) ($setting->pincode ?? '');
                $this->isGstBillingActive = (bool) ($setting->is_gst_billing_active ?? false);
                $this->gstPricingType = (string) ($setting->gst_pricing_type ?: 'included');
                $this->gstPercentage = (float) ($setting->gst_percentage ?? 18.00);
                $this->gstNumber = (string) ($setting->gst_number ?? '');
            } elseif ($location) {
                $this->taxCompanyName = $location->name;
                $this->taxAddress = $location->address;
                $this->taxCompanyEmail = (string) ($location->email ?? '');
                $this->taxCompanyPhone = (string) ($location->contact_number ?? '');
            }

            $this->isOnlinePaymentActive = (bool) ($turf->is_online_payment_active ?? true);
            $this->isPartPaymentActive = (bool) ($turf->is_part_payment_active ?? false);
            $this->isPayAtLocationActive = (bool) ($turf->is_pay_at_location_active ?? false);
            $this->partPaymentType = (string) ($turf->part_payment_type ?: 'percentage');
            $this->partPaymentValue = $turf->part_payment_value ?? 50;
            $this->isBookingOpen = (bool) ($turf->is_booking_open ?? true);
            $this->bookingOpenDays = (int) ($turf->booking_open_days ?: 90);
            $this->isManagerBookingActive = (bool) ($turf->is_manager_booking_active ?? true);
            $this->isCancellationActive = (bool) ($turf->is_cancellation_active ?? false);
            $this->cancellationHours = (int) ($turf->cancellation_hours ?? 48);
            $this->cancellationFee = $turf->cancellation_fee ?? 0;
            $this->isRescheduleActive = (bool) ($turf->is_reschedule_active ?? true);
            $this->maxReschedules = (int) ($turf->max_reschedules ?: 2);
        } else {
            $this->turfMode = 'create';
        }

        // Hydrate Owner Payout details
        $owner = $turf?->owner() ?? $location?->user ?? auth()->user();
        if ($owner) {
            $this->payoutMethod = (string) ($owner->payout_method ?: 'bank');
            $this->bankAccountName = (string) ($owner->bank_account_name ?? '');
            $this->bankAccountNumber = (string) ($owner->bank_account_number ?? '');
            $this->bankIfsc = (string) ($owner->bank_ifsc ?? '');
            $this->upiId = (string) ($owner->upi_id ?? '');
        }
    }

    public function switchLocationMode(string $mode): void
    {
        $this->locationMode = $mode;
        if ($mode === 'create') {
            $this->locationId = null;
            $this->locationName = '';
            $this->locationAddress = '';
            $this->locationLatitude = '';
            $this->locationLongitude = '';
            $this->locationContactNumber = '';
            $this->locationEmail = '';
        } elseif ($mode === 'existing') {
            $first = Location::manageable()->first();
            if ($first) {
                $this->selectExistingLocation($first->id);
            }
        }
    }

    public function selectExistingLocation(int $id): void
    {
        $loc = Location::manageable()->findOrFail($id);
        $this->locationMode = 'existing';
        $this->locationId = $loc->id;
        $this->locationName = (string) $loc->name;
        $this->locationAddress = (string) $loc->address;
        $this->locationLatitude = $loc->latitude !== null ? (string) $loc->latitude : '';
        $this->locationLongitude = $loc->longitude !== null ? (string) $loc->longitude : '';
        $this->locationContactNumber = (string) ($loc->contact_number ?? '');
        $this->locationEmail = (string) ($loc->email ?? '');
    }

    public function useOwnerContactForLocation(): void
    {
        $ownerId = auth()->user()->getOwnerId();
        $owner = User::find($ownerId) ?: auth()->user();
        $this->locationContactNumber = (string) ($owner->mobile ?? '');
        $this->locationEmail = (string) ($owner->email ?? '');
    }

    public function switchTurfMode(string $mode): void
    {
        $this->turfMode = $mode;
        if ($mode === 'create') {
            $this->turfId = null;
            $this->turfName = '';
            $this->turfType = 'Synthetic';
            $this->turfArea = '';
            $this->turfDescription = '';
            $this->turfIsActive = true;
        } elseif ($mode === 'existing' && $this->locationId) {
            $firstTurf = Turf::manageable()->where('location_id', $this->locationId)->first();
            if ($firstTurf) {
                $this->selectExistingTurf($firstTurf->id);
            }
        }
    }

    public function selectExistingTurf(int $id): void
    {
        $turf = Turf::manageable()->findOrFail($id);
        $this->turfMode = 'existing';
        $this->turfId = $turf->id;
        $this->locationId = $turf->location_id;
        session([
            'active_location_id' => $turf->location_id,
            'active_turf_id' => $turf->id,
        ]);
        $this->hydrateFromModels();
    }

    // Step 3 Photo upload & reorder helpers
    public function updatedPhotoFiles(): void
    {
        $this->uploadWizardPhotos();
    }

    public function uploadWizardPhotos(): void
    {
        if (empty($this->photoFiles) || !$this->turfId) {
            return;
        }

        $this->validate([
            'photoFiles.*' => 'required|image|max:2048',
        ], [
            'photoFiles.*.image' => 'Each uploaded file must be a valid image.',
            'photoFiles.*.max' => 'Each photo must not exceed 2 MB.',
        ]);

        $turf = Turf::manageable()->findOrFail($this->turfId);
        $existingCount = $turf->photos()->count();
        $maxOrder = (int) $turf->photos()->max('sort_order');

        foreach ($this->photoFiles as $file) {
            if ($existingCount >= 10) {
                session()->flash('error', 'Maximum 10 photos allowed per turf.');
                break;
            }
            $path = $file->store('turf_photos', 'public');
            $maxOrder++;
            $existingCount++;

            TurfPhoto::create([
                'turf_id' => $turf->id,
                'photo' => $path,
                'is_active' => true,
                'sort_order' => $maxOrder,
            ]);
        }

        $this->photoFiles = [];
        $this->resetErrorBag('photosStep');
        session()->flash('status', 'Photo(s) uploaded successfully.');
    }

    public function setAsCoverPhoto(int $photoId): void
    {
        if (!$this->turfId) {
            return;
        }
        $turf = Turf::manageable()->findOrFail($this->turfId);
        $photos = $turf->photos()->get();
        $target = $photos->firstWhere('id', $photoId);
        if (!$target) {
            return;
        }

        $target->update(['sort_order' => 0, 'is_active' => true]);
        $order = 1;
        foreach ($photos as $p) {
            if ($p->id !== $target->id) {
                $p->update(['sort_order' => $order++]);
            }
        }
    }

    public function movePhotoUp(int $photoId): void
    {
        $this->reorderPhoto($photoId, -1);
    }

    public function movePhotoDown(int $photoId): void
    {
        $this->reorderPhoto($photoId, 1);
    }

    protected function reorderPhoto(int $photoId, int $direction): void
    {
        if (!$this->turfId) {
            return;
        }
        $turf = Turf::manageable()->findOrFail($this->turfId);
        $photos = $turf->photos()->get()->values();
        $idx = $photos->search(fn ($p) => $p->id === $photoId);
        if ($idx === false) {
            return;
        }
        $swapIdx = $idx + $direction;
        if (!isset($photos[$swapIdx])) {
            return;
        }

        foreach ($photos as $i => $p) {
            $newOrder = $i;
            if ($i === $idx) {
                $newOrder = $swapIdx;
            } elseif ($i === $swapIdx) {
                $newOrder = $idx;
            }
            $p->update(['sort_order' => $newOrder]);
        }
    }

    public function toggleWizardPhotoActive(int $photoId): void
    {
        if (!$this->turfId) {
            return;
        }
        $turf = Turf::manageable()->findOrFail($this->turfId);
        $photo = $turf->photos()->findOrFail($photoId);
        $photo->update(['is_active' => !$photo->is_active]);
    }

    public function deleteWizardPhoto(int $photoId): void
    {
        if (!$this->turfId) {
            return;
        }
        $turf = Turf::manageable()->findOrFail($this->turfId);
        $photo = $turf->photos()->findOrFail($photoId);
        if (Storage::disk('public')->exists($photo->photo)) {
            Storage::disk('public')->delete($photo->photo);
        }
        $photo->delete();
    }

    // Steps 4, 5, 6 helpers
    public function selectAllCatalog(string $type): void
    {
        if ($type === 'facilities') {
            $this->selectedFacilityIds = Facility::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } elseif ($type === 'equipments') {
            $this->selectedEquipmentIds = Equipment::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } elseif ($type === 'sports') {
            $this->selectedSportIds = Sport::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        }
    }

    public function clearAllCatalog(string $type): void
    {
        if ($type === 'facilities') {
            $this->selectedFacilityIds = [];
        } elseif ($type === 'equipments') {
            $this->selectedEquipmentIds = [];
        } elseif ($type === 'sports') {
            $this->selectedSportIds = [];
        }
    }

    // Step 7 Slot helpers
    public function selectAllSlots(): void
    {
        $ids = Slot::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        sort($ids);
        $this->selectedSlotIds = $ids;
    }

    public function deselectAllSlots(): void
    {
        $this->selectedSlotIds = [];
    }

    public function selectCategorySlots(int $categoryId): void
    {
        $slotIds = Slot::where('is_active', true)
            ->where('slot_category_id', $categoryId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $merged = array_values(array_unique(array_merge($this->selectedSlotIds, $slotIds)));
        sort($merged);
        $this->selectedSlotIds = $merged;
    }

    public function deselectCategorySlots(int $categoryId): void
    {
        $slotIds = Slot::where('is_active', true)
            ->where('slot_category_id', $categoryId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $diff = array_values(array_diff($this->selectedSlotIds, $slotIds));
        sort($diff);
        $this->selectedSlotIds = $diff;
    }

    public function selectSlotsByTimeWindow(): void
    {
        $allSlots = Slot::where('is_active', true)->get();
        $matched = [];
        foreach ($allSlots as $slot) {
            if (TurfPricingService::slotMatchesRange((string) $slot->from_time, (string) $slot->to_time, $this->slotWindowFrom, $this->slotWindowTo)) {
                $matched[] = (string) $slot->id;
            }
        }
        $this->selectedSlotIds = $matched;
    }

    // Step 8 Pricing helpers
    public function addPricingTimeRange(string $group, ?int $groupIndex = null): void
    {
        if ($group === 'all') {
            $this->timeRangesAll[] = ['from' => '00:00', 'to' => '23:59', 'rate' => ''];
        } elseif ($group === 'group' && $groupIndex !== null) {
            $this->dayGroups[$groupIndex]['timeRanges'][] = ['from' => '00:00', 'to' => '23:59', 'rate' => ''];
        }
    }

    public function removePricingTimeRange(string $group, int $index, ?int $groupIndex = null): void
    {
        if ($group === 'all') {
            unset($this->timeRangesAll[$index]);
            $this->timeRangesAll = array_values($this->timeRangesAll);
        } elseif ($group === 'group' && $groupIndex !== null) {
            unset($this->dayGroups[$groupIndex]['timeRanges'][$index]);
            $this->dayGroups[$groupIndex]['timeRanges'] = array_values($this->dayGroups[$groupIndex]['timeRanges']);
        }
    }

    public function addPricingDayGroup(): void
    {
        $this->dayGroups[] = [
            'days' => [],
            'sameRateThroughoutDay' => 'yes',
            'flatRate' => '',
            'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']],
        ];
    }

    public function removePricingDayGroup(int $index): void
    {
        unset($this->dayGroups[$index]);
        $this->dayGroups = array_values($this->dayGroups);
    }

    public function toggleDayInGroup(int $groupIndex, string $dayKey): void
    {
        $days = $this->dayGroups[$groupIndex]['days'] ?? [];
        if (in_array($dayKey, $days, true)) {
            $days = array_values(array_diff($days, [$dayKey]));
        } else {
            $days[] = $dayKey;
        }
        $this->dayGroups[$groupIndex]['days'] = $days;
    }

    // Step 9 Taxation helpers
    public function updatedTaxState($value): void
    {
        $resolved = TurfTaxationService::resolveState((string) $value);
        $this->taxState = $resolved['state'];
        $this->taxStateCode = $resolved['state_code'];
    }

    public function updatedGstNumber(): void
    {
        $this->gstNumber = strtoupper(trim($this->gstNumber));
        if (strlen($this->gstNumber) >= 2) {
            $prefix = substr($this->gstNumber, 0, 2);
            if (IndianStates::isValid($prefix)) {
                $this->taxState = (string) IndianStates::name($prefix);
                $this->taxStateCode = $prefix;
            }
        }
    }

    public function copyLocationToTaxation(): void
    {
        $loc = $this->locationId ? Location::manageable()->find($this->locationId) : null;
        if ($loc) {
            $this->taxCompanyName = (string) $loc->name;
            $this->taxAddress = (string) $loc->address;
            $this->taxCompanyPhone = (string) ($loc->contact_number ?? '');
            $this->taxCompanyEmail = (string) ($loc->email ?? '');
        }
    }

    // Step Navigation & Saving
    public function goToStep(int $targetStep): void
    {
        $progress = $this->computeProgress();
        if (!isset($progress['steps'][$targetStep])) {
            return;
        }
        $meta = $progress['steps'][$targetStep];
        if ($meta['hidden'] || !$meta['unlocked']) {
            return;
        }
        $this->resetErrorBag();
        $this->currentStep = $targetStep;
    }

    public function previousStep(): void
    {
        $progress = $this->computeProgress();
        for ($s = $this->currentStep - 1; $s >= 1; $s--) {
            if (!($progress['steps'][$s]['hidden'] ?? false)) {
                $this->currentStep = $s;
                return;
            }
        }
    }

    public function skipPayoutStep(): void
    {
        $this->currentStep = 12;
    }

    public function saveAndExit()
    {
        if ($this->currentStep < 12) {
            if (!$this->saveCurrentStepData()) {
                return;
            }
        }
        return redirect()->route('turf.dashboard');
    }

    public function saveAndContinue(): void
    {
        if (!$this->saveCurrentStepData()) {
            return;
        }

        $progress = $this->computeProgress();
        for ($s = $this->currentStep + 1; $s <= 12; $s++) {
            if (!($progress['steps'][$s]['hidden'] ?? false)) {
                $this->currentStep = $s;
                return;
            }
        }
    }

    protected function saveCurrentStepData(): bool
    {
        $this->resetErrorBag();

        if ($this->currentStep === 1) {
            $this->validate([
                'locationName' => 'required|string|max:150',
                'locationAddress' => 'required|string|max:500',
                'locationLatitude' => 'required|numeric|between:-90,90',
                'locationLongitude' => 'required|numeric|between:-180,180',
                'locationContactNumber' => 'nullable|string|max:30',
                'locationEmail' => 'nullable|email|max:100',
            ], [
                'locationName.required' => 'Enter the venue / location name.',
                'locationAddress.required' => 'Enter the complete street address.',
                'locationLatitude.required' => 'Map pin latitude is required. Click on the map or use GPS.',
                'locationLongitude.required' => 'Map pin longitude is required. Click on the map or use GPS.',
            ]);

            $data = [
                'user_id' => auth()->user()->getOwnerId(),
                'name' => $this->locationName,
                'address' => $this->locationAddress,
                'latitude' => (float) $this->locationLatitude,
                'longitude' => (float) $this->locationLongitude,
                'contact_number' => $this->locationContactNumber ?: null,
                'email' => $this->locationEmail ?: null,
            ];

            if ($this->locationMode === 'existing' && $this->locationId) {
                $location = Location::manageable()->findOrFail($this->locationId);
                $location->update($data);
            } else {
                $location = Location::create($data);
                $this->locationId = $location->id;
                $this->locationMode = 'existing';
            }

            session(['active_location_id' => $location->id]);
            $this->dispatch('locations-updated');
            return true;
        }

        if ($this->currentStep === 2) {
            if (!$this->locationId) {
                $this->currentStep = 1;
                return false;
            }

            $this->validate([
                'turfName' => 'required|string|max:150',
                'turfType' => 'required|in:Synthetic,Hard,Other',
                'turfArea' => 'nullable|string|max:100',
                'turfDescription' => 'nullable|string|max:1000',
                'turfIsActive' => 'boolean',
            ], [
                'turfName.required' => 'Enter the turf or court name.',
            ]);

            $location = Location::manageable()->findOrFail($this->locationId);

            $data = [
                'location_id' => $location->id,
                'name' => $this->turfName,
                'type' => $this->turfType,
                'area' => $this->turfArea ?: null,
                'description' => $this->turfDescription ?: null,
                'is_active' => $this->turfIsActive,
            ];

            if ($this->turfMode === 'existing' && $this->turfId) {
                $turf = Turf::manageable()->findOrFail($this->turfId);
                $turf->update($data);
            } else {
                $data['status'] = 'Draft';
                $data['onboarded_by'] = session('impersonator_id') ?: auth()->id();
                $turf = Turf::create($data);
                $this->turfId = $turf->id;
                $this->turfMode = 'existing';
            }

            session([
                'active_location_id' => $location->id,
                'active_turf_id' => $turf->id,
            ]);
            $this->dispatch('turfs-updated');
            return true;
        }

        // Steps 3..11 require a valid manageable turf
        $turf = $this->turfId ? Turf::manageable()->find($this->turfId) : null;
        if (!$turf) {
            $this->currentStep = 2;
            return false;
        }

        if ($this->currentStep === 3) {
            if (!empty($this->photoFiles)) {
                $this->uploadWizardPhotos();
            }
            if ($turf->photos()->where('is_active', true)->count() < 1) {
                $this->addError('photosStep', 'Please upload at least 1 active photo of the turf before proceeding.');
                return false;
            }
            return true;
        }

        if ($this->currentStep === 4) {
            $validIds = Facility::where('is_active', true)->whereIn('id', $this->selectedFacilityIds)->pluck('id')->toArray();
            $turf->facilities()->sync($validIds);
            return true;
        }

        if ($this->currentStep === 5) {
            $validIds = Equipment::where('is_active', true)->whereIn('id', $this->selectedEquipmentIds)->pluck('id')->toArray();
            $turf->turfEquipments()->sync($validIds);
            return true;
        }

        if ($this->currentStep === 6) {
            $validIds = Sport::where('is_active', true)->whereIn('id', $this->selectedSportIds)->pluck('id')->toArray();
            if (count($validIds) < 1) {
                $this->addError('sportsStep', 'Please select at least 1 sport supported on this turf.');
                return false;
            }
            $turf->sports()->sync($validIds);
            return true;
        }

        if ($this->currentStep === 7) {
            $allActiveSlots = Slot::where('is_active', true)->get();
            $selectedCount = 0;
            $syncData = [];
            foreach ($allActiveSlots as $slot) {
                $isSelected = in_array((string) $slot->id, $this->selectedSlotIds, true);
                if ($isSelected) {
                    $selectedCount++;
                }
                $syncData[$slot->id] = ['is_active' => $isSelected];
            }

            if ($selectedCount < 1) {
                $this->addError('slotsStep', 'Please select at least 1 operating time slot before proceeding.');
                return false;
            }

            // Preserve existing pivot prices for slots that were already attached
            $existingPivots = $turf->slots()->get()->keyBy('id');
            foreach ($syncData as $slotId => &$pivotAttrs) {
                if (isset($existingPivots[$slotId])) {
                    $ep = $existingPivots[$slotId]->pivot;
                    foreach (TurfPricingService::DAYS as $d) {
                        $pivotAttrs[$d] = $ep->{$d};
                    }
                }
            }

            $turf->slots()->sync($syncData);
            return true;
        }

        if ($this->currentStep === 8) {
            $activeSlots = $turf->slots()->wherePivot('is_active', true)->orderBy('from_time', 'asc')->get();
            if ($activeSlots->isEmpty()) {
                $this->addError('pricingStep', 'No active slots found. Go back to Step 7 and select operating slots.');
                return false;
            }

            $computed = TurfPricingService::computePrices(
                $activeSlots,
                $this->sameRateThroughoutWeek,
                $this->sameRateThroughoutDayAll,
                $this->flatRateAll,
                $this->timeRangesAll,
                $this->dayGroups
            );

            $unpriced = TurfPricingService::findUnpricedSlots($activeSlots, $computed);
            if (!empty($unpriced)) {
                $this->addError('pricingStep', count($unpriced) . ' active slot(s) still have no rate (or ₹0). Ensure your rate rules cover all 7 days and all active slot times.');
                return false;
            }

            TurfPricingService::applyPricingToTurf(
                $turf,
                $this->sameRateThroughoutWeek,
                $this->sameRateThroughoutDayAll,
                $this->flatRateAll,
                $this->timeRangesAll,
                $this->dayGroups,
                false
            );
            return true;
        }

        if ($this->currentStep === 9) {
            $resolved = TurfTaxationService::resolveState($this->taxState);
            $this->taxState = $resolved['state'];
            $this->taxStateCode = $resolved['state_code'];

            $validated = $this->validate([
                'taxCompanyName' => 'nullable|string|max:150',
                'taxCompanyEmail' => 'nullable|email|max:150',
                'taxCompanyPhone' => 'nullable|string|max:20',
                'taxAddress' => 'nullable|string|max:500',
                'taxCity' => 'nullable|string|max:100',
                'taxState' => 'required|string|max:100',
                'taxPincode' => 'nullable|string|max:15',
                'isGstBillingActive' => 'required|boolean',
                'gstPricingType' => 'required|in:included,excluded',
                'gstPercentage' => 'required|numeric|min:0|max:100',
                'gstNumber' => [
                    $this->isGstBillingActive ? 'required' : 'nullable',
                    'string',
                    'max:20',
                    'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
                ],
            ], [
                'taxState.required' => 'Select the state where this turf is located.',
                'gstNumber.required' => 'Enter your 15-character GSTIN to enable GST billing.',
                'gstNumber.regex' => 'Please enter a valid 15-character GSTIN format (e.g., 27AAAAA0000A1Z5).',
            ]);

            $payload = [
                'company_name' => $validated['taxCompanyName'] ?: null,
                'company_email' => $validated['taxCompanyEmail'] ?: null,
                'company_phone' => $validated['taxCompanyPhone'] ?: null,
                'address' => $validated['taxAddress'] ?: null,
                'city' => $validated['taxCity'] ?: null,
                'state' => $this->taxState,
                'country' => 'India',
                'pincode' => $validated['taxPincode'] ?: null,
                'is_gst_billing_active' => (bool) $this->isGstBillingActive,
                'gst_pricing_type' => $this->gstPricingType,
                'gst_percentage' => (float) $this->gstPercentage,
                'gst_number' => $this->isGstBillingActive ? ($validated['gstNumber'] ?: null) : null,
            ];

            $res = TurfTaxationService::validateAndSave($turf, $payload, $this->taxStateCode);
            if (!$res['ok']) {
                $this->addError($res['field'], $res['message']);
                return false;
            }
            return true;
        }

        if ($this->currentStep === 10) {
            $rules = [
                'isOnlinePaymentActive' => 'required|boolean',
                'isPartPaymentActive' => 'required|boolean',
                'isPayAtLocationActive' => 'required|boolean',
                'partPaymentType' => 'required|in:percentage,flat',
                'partPaymentValue' => 'required|numeric|min:0',
                'isBookingOpen' => 'required|boolean',
                'bookingOpenDays' => 'required|integer|in:30,60,90',
                'isManagerBookingActive' => 'required|boolean',
                'isCancellationActive' => 'required|boolean',
                'cancellationHours' => 'required|integer|min:0',
                'cancellationFee' => 'required|numeric|min:0',
                'isRescheduleActive' => 'required|boolean',
                'maxReschedules' => 'required|integer|min:1|max:10',
            ];
            if ($this->isPartPaymentActive && $this->partPaymentType === 'percentage') {
                $rules['partPaymentValue'] .= '|max:100';
            }
            $this->validate($rules);

            $turf->update([
                'is_online_payment_active' => $this->isOnlinePaymentActive,
                'is_part_payment_active' => $this->isPartPaymentActive,
                'is_pay_at_location_active' => $this->isPayAtLocationActive,
                'part_payment_type' => $this->partPaymentType,
                'part_payment_value' => $this->partPaymentValue,
                'is_booking_open' => $this->isBookingOpen,
                'booking_open_days' => $this->bookingOpenDays,
                'is_manager_booking_active' => $this->isManagerBookingActive,
                'is_cancellation_active' => $this->isCancellationActive,
                'cancellation_hours' => $this->cancellationHours,
                'cancellation_fee' => $this->cancellationFee,
                'is_reschedule_active' => $this->isRescheduleActive,
                'max_reschedules' => $this->maxReschedules,
            ]);
            return true;
        }

        if ($this->currentStep === 11) {
            // Optional payout step: if all fields are blank, allow skipping to Step 12
            $hasInput = ($this->payoutMethod === 'bank' && (trim($this->bankAccountNumber) !== '' || trim($this->bankIfsc) !== ''))
                || ($this->payoutMethod === 'upi' && trim($this->upiId) !== '');

            if (!$hasInput) {
                return true;
            }

            $details = PayoutDestination::normalize(
                $this->payoutMethod,
                $this->bankAccountName,
                $this->bankAccountNumber,
                $this->bankIfsc,
                $this->upiId
            );

            if ($problem = PayoutDestination::problem($details)) {
                $fieldMap = [
                    'payout_method' => 'payoutMethod',
                    'bank_account_name' => 'bankAccountName',
                    'bank_account_number' => 'bankAccountNumber',
                    'bank_ifsc' => 'bankIfsc',
                    'upi_id' => 'upiId',
                ];
                $this->addError($fieldMap[$problem['field']] ?? 'bankAccountNumber', $problem['message']);
                return false;
            }

            $owner = $turf->owner() ?? auth()->user();
            $owner?->update($details);
            return true;
        }

        return true;
    }

    public function submitForVerification()
    {
        $user = auth()->user();
        $isManagerOnly = $user
            && !$user->hasRole('turf-admin')
            && !$user->hasRole('saas-admin')
            && !$user->hasRole('marketing')
            && $user->hasRole('manager');

        if ($isManagerOnly) {
            abort(403, 'Managers cannot submit a turf for verification. Please ask the Turf Owner.');
        }

        $turf = $this->turfId ? Turf::manageable()->findOrFail($this->turfId) : null;
        if (!$turf) {
            return;
        }

        $progress = $this->computeProgress();
        if (!$progress['required_completed']) {
            $this->currentStep = $progress['first_incomplete_step'];
            session()->flash('error', 'Please complete all required onboarding steps before submitting.');
            return;
        }

        $onboardedById = session('impersonator_id') ?: $user->id;
        $submittedByUser = User::find($onboardedById) ?: $user;

        $wasDraftOrRejected = in_array($turf->status, ['Draft', 'Rejected'], true);

        // Status rules:
        // Draft / Rejected -> Pending (sets submitted_at & onboarded_by, sends email)
        // Pending -> stays Pending
        // Approved -> stays Approved
        $newStatus = $wasDraftOrRejected ? 'Pending' : $turf->status;

        $turf->update([
            'status' => $newStatus,
            'onboarded_by' => $turf->onboarded_by ?: $onboardedById,
            'submitted_at' => $wasDraftOrRejected ? now() : ($turf->submitted_at ?: now()),
        ]);

        if ($wasDraftOrRejected) {
            $verifiers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['saas-admin', 'marketing']);
            })->get();

            if ($verifiers->isNotEmpty()) {
                Notification::send($verifiers, new TurfSubmittedForVerification($turf->fresh(['location.user', 'onboardedBy']), $submittedByUser));
            }

            session()->flash('status', "🎉 {$turf->name} has been submitted for verification! Our team has been notified via email.");
        } else {
            session()->flash('status', "✓ All setup details for {$turf->name} have been saved.");
        }

        return redirect()->route('turf.dashboard');
    }

    protected function computeProgress(): array
    {
        $turf = $this->turfId ? Turf::manageable()->find($this->turfId) : null;
        $location = $this->locationId ? Location::manageable()->find($this->locationId) : ($turf?->location);

        return TurfOnboardingProgress::evaluate($turf, $location, auth()->user());
    }

    public function with(): array
    {
        $manageableLocations = Location::manageable()->orderBy('name', 'asc')->get();
        $currentLocation = $this->locationId ? $manageableLocations->firstWhere('id', $this->locationId) : null;
        $locationTurfs = $currentLocation
            ? Turf::manageable()->where('location_id', $currentLocation->id)->orderBy('name', 'asc')->get()
            : collect();
        $allManageableTurfs = Turf::manageable()->with('location')->orderBy('name', 'asc')->get();

        $currentTurf = $this->turfId ? Turf::manageable()->find($this->turfId) : null;
        $turfPhotos = $currentTurf ? $currentTurf->photos()->get() : collect();

        $allFacilities = Facility::where('is_active', true)->orderBy('name', 'asc')->get();
        $allEquipments = Equipment::where('is_active', true)->orderBy('name', 'asc')->get();
        $allSports = Sport::where('is_active', true)->orderBy('name', 'asc')->get();
        $allSlots = Slot::where('is_active', true)->orderBy('from_time', 'asc')->get();
        $availableCategories = SlotCategory::where('is_active', true)->orderBy('sort_order', 'asc')->get();

        $activeTurfSlots = $currentTurf
            ? $currentTurf->slots()->wherePivot('is_active', true)->orderBy('from_time', 'asc')->get()
            : collect();

        $computedPricingPreview = [];
        $unpricedSlots = [];
        if ($activeTurfSlots->isNotEmpty()) {
            $computedPricingPreview = TurfPricingService::computePrices(
                $activeTurfSlots,
                $this->sameRateThroughoutWeek,
                $this->sameRateThroughoutDayAll,
                $this->flatRateAll,
                $this->timeRangesAll,
                $this->dayGroups
            );
            $unpricedSlots = TurfPricingService::findUnpricedSlots($activeTurfSlots, $computedPricingPreview);
        }

        $progress = TurfOnboardingProgress::evaluate($currentTurf, $currentLocation, auth()->user());

        $user = auth()->user();
        $isManagerOnly = $user
            && !$user->hasRole('turf-admin')
            && !$user->hasRole('saas-admin')
            && !$user->hasRole('marketing')
            && $user->hasRole('manager');

        return [
            'manageableLocations' => $manageableLocations,
            'currentLocation' => $currentLocation,
            'locationTurfs' => $locationTurfs,
            'allManageableTurfs' => $allManageableTurfs,
            'currentTurf' => $currentTurf,
            'turfPhotos' => $turfPhotos,
            'allFacilities' => $allFacilities,
            'allEquipments' => $allEquipments,
            'allSports' => $allSports,
            'allSlots' => $allSlots,
            'availableCategories' => $availableCategories,
            'activeTurfSlots' => $activeTurfSlots,
            'computedPricingPreview' => $computedPricingPreview,
            'unpricedSlots' => $unpricedSlots,
            'progress' => $progress,
            'isManagerOnly' => $isManagerOnly,
        ];
    }
}; ?>

<div class="w-full space-y-6">
    <!-- Top Wizard Header Bar -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-gray-100 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-3.5">
            <div class="h-12 w-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-emerald-500 text-white flex items-center justify-center font-black text-xl shadow-sm shrink-0">
                ✨
            </div>
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-xl font-black text-gray-900 tracking-tight">{{ __('Turf Onboarding Setup Wizard') }}</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {{ $progress['completed_count'] }} / {{ $progress['total_visible_steps'] }} {{ __('Steps Complete') }} ({{ $progress['percentage'] }}%)
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if ($currentTurf)
                        {{ __('Configuring:') }} <strong class="text-indigo-600">{{ $currentTurf->name }}</strong>
                        @if ($currentLocation)
                            <span class="text-gray-300 mx-1">•</span>
                            <span>{{ $currentLocation->name }}</span>
                        @endif
                    @else
                        {{ __('Follow the guided steps below to set up your venue location, turf court, slots, pricing, and GST.') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            @if ($allManageableTurfs->count() > 1)
                <select wire:change="selectExistingTurf($event.target.value)"
                        class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-800 bg-gray-50">
                    @foreach ($allManageableTurfs as $mTurf)
                        <option value="{{ $mTurf->id }}" @selected($mTurf->id === $turfId)>
                            🏟️ {{ $mTurf->name }} ({{ $mTurf->location?->name }})
                        </option>
                    @endforeach
                </select>
            @endif

            @if ($currentTurf)
                <button type="button"
                        wire:click="startNewTurfSetup"
                        class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/60 text-xs font-black uppercase tracking-wider transition cursor-pointer">
                    + {{ __('Setup New Turf') }}
                </button>
            @endif

            <button type="button"
                    wire:click="saveAndExit"
                    class="px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition cursor-pointer">
                {{ __('Save & Exit') }}
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2.5">
            <span>✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2.5">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Responsive Stepper (Mobile Dropdown + Progress Bar & Desktop 12-Pill Bar) -->
    <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-2xs space-y-3">
        <!-- Overall Progress Bar -->
        <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full bg-gradient-to-r from-indigo-600 via-violet-500 to-emerald-500 transition-all duration-300"
                 style="width: {{ max(5, $progress['percentage']) }}%;"></div>
        </div>

        <!-- Mobile Step Selector -->
        <div class="lg:hidden flex items-center justify-between gap-3 pt-1">
            <span class="text-xs font-black text-gray-800">
                {{ __('Step') }} {{ $currentStep }}: {{ $progress['steps'][$currentStep]['title'] ?? '' }}
            </span>
            <select wire:change="goToStep($event.target.value)"
                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold bg-gray-50 text-gray-800">
                @foreach ($progress['steps'] as $sNum => $sMeta)
                    @if (!$sMeta['hidden'])
                        <option value="{{ $sNum }}" @selected($currentStep === $sNum) @disabled(!$sMeta['unlocked'])>
                            {{ $sMeta['completed'] ? '✓ ' : '' }}{{ $sNum }}. {{ $sMeta['title'] }}
                        </option>
                    @endif
                @endforeach
            </select>
        </div>

        <!-- Desktop Horizontal Stepper -->
        <div class="hidden lg:grid lg:grid-cols-12 gap-1.5 pt-1">
            @foreach ($progress['steps'] as $sNum => $sMeta)
                @if (!$sMeta['hidden'])
                    @php
                        $isCurrent = $currentStep === $sNum;
                        $isDone = $sMeta['completed'];
                        $isUnlocked = $sMeta['unlocked'];
                    @endphp
                    <button type="button"
                            wire:click="goToStep({{ $sNum }})"
                            @disabled(!$isUnlocked)
                            title="{{ $sMeta['title'] }} — {{ $sMeta['summary'] }}"
                            class="p-2 rounded-xl border text-left transition flex flex-col justify-between {{ $isCurrent ? 'border-indigo-600 bg-indigo-50/70 ring-2 ring-indigo-500/20 cursor-pointer' : ($isDone ? 'border-emerald-200 bg-emerald-50/40 hover:bg-emerald-50 cursor-pointer' : ($isUnlocked ? 'border-gray-200 bg-white hover:border-indigo-200 cursor-pointer' : 'border-gray-100 bg-gray-50/60 opacity-50 cursor-not-allowed')) }}">
                        <div class="flex items-center justify-between gap-1">
                            <span class="h-5 w-5 rounded-lg text-[10px] font-black flex items-center justify-center {{ $isCurrent ? 'bg-indigo-600 text-white' : ($isDone ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600') }}">
                                {{ $isDone ? '✓' : $sNum }}
                            </span>
                            @if ($sMeta['required'] && !$isDone)
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400" title="Required"></span>
                            @endif
                        </div>
                        <div class="mt-1.5 text-[10px] font-black truncate {{ $isCurrent ? 'text-indigo-900' : ($isDone ? 'text-emerald-900' : 'text-gray-700') }}">
                            {{ $sMeta['short'] }}
                        </div>
                    </button>
                @endif
            @endforeach
        </div>
    </div>

    <!-- Active Step Card Body -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-xs">
        @if ($currentStep === 1)
            @include('livewire.turf.wizard.step-1-location')
        @elseif ($currentStep === 2)
            @include('livewire.turf.wizard.step-2-turf')
        @elseif ($currentStep === 3)
            @include('livewire.turf.wizard.step-3-photos')
        @elseif (in_array($currentStep, [4, 5, 6], true))
            @include('livewire.turf.wizard.step-4-5-6-catalog')
        @elseif ($currentStep === 7)
            @include('livewire.turf.wizard.step-7-slots')
        @elseif ($currentStep === 8)
            @include('livewire.turf.wizard.step-8-pricing')
        @elseif (in_array($currentStep, [9, 10, 11], true))
            @include('livewire.turf.wizard.step-9-10-11-config')
        @elseif ($currentStep === 12)
            @include('livewire.turf.wizard.step-12-review')
        @endif

        <!-- Wizard Step Footer Controls -->
        <div class="mt-8 pt-5 border-t border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div>
                @if ($currentStep > 1)
                    <button type="button"
                            wire:click="previousStep"
                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-black uppercase tracking-wider transition cursor-pointer">
                        ← {{ __('Previous Step') }}
                    </button>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                @if ($currentStep < 12)
                    <button type="button"
                            wire:click="saveAndContinue"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider shadow-md shadow-indigo-500/20 transition cursor-pointer">
                        <span>{{ __('Save & Continue') }} →</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

