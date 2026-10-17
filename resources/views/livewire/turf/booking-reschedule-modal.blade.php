<?php

use App\Models\BookingDate;
use App\Models\Turf;
use App\Services\BookingRescheduleService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $isOpen = false;
    public ?int $bookingDateId = null;
    public ?BookingDate $bookingDate = null;

    public string $targetDate = '';
    public array $selectedSlotIds = [];
    public ?string $reason = null;

    public array $availableSlots = [];
    public ?array $previewData = null;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    public int $reschedulesUsed = 0;
    public int $reschedulesAllowed = 2;
    public bool $isRescheduleActive = true;

    #[On('open-reschedule-modal')]
    public function open(int $bookingDateId): void
    {
        $this->resetState();
        $this->bookingDateId = $bookingDateId;

        $user = auth()->user();
        $bDate = BookingDate::with(['booking.turf.location', 'bookingSlots.slot.category', 'reschedules'])
            ->find($bookingDateId);

        if (!$bDate) {
            $this->errorMessage = 'Session not found.';
            return;
        }

        // Authorize staff
        $isSaasAdmin = $user && $user->hasRole('saas-admin');
        $isManageable = $user && $user->hasAnyRole(['turf-admin', 'manager', 'admin']) &&
            Turf::manageable($user)->where('turfs.id', $bDate->booking->turf_id)->exists();

        if (!$isSaasAdmin && !$isManageable) {
            $this->errorMessage = 'Unauthorized action.';
            return;
        }

        $this->bookingDate = $bDate;
        $this->targetDate = Carbon::today('Asia/Kolkata')->toDateString();

        $this->loadSlots();
        $this->isOpen = true;
    }

    public function updatedTargetDate(): void
    {
        $this->selectedSlotIds = [];
        $this->previewData = null;
        $this->errorMessage = null;
        $this->loadSlots();
    }

    public function loadSlots(): void
    {
        if (!$this->bookingDate || !$this->targetDate) {
            return;
        }

        try {
            $service = new BookingRescheduleService();
            $data = $service->slots($this->bookingDate, $this->targetDate);

            $this->availableSlots = $data['slots'];
            $this->reschedulesUsed = $data['reschedules_used'];
            $this->reschedulesAllowed = $data['reschedules_allowed'];
            $this->isRescheduleActive = $data['is_reschedule_active'];
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleSlot(int $slotId): void
    {
        if (in_array($slotId, $this->selectedSlotIds)) {
            $this->selectedSlotIds = array_values(array_diff($this->selectedSlotIds, [$slotId]));
        } else {
            $this->selectedSlotIds[] = $slotId;
        }

        $this->updatePreview();
    }

    public function updatePreview(): void
    {
        $this->errorMessage = null;
        $this->previewData = null;

        if (empty($this->selectedSlotIds) || !$this->bookingDate) {
            return;
        }

        try {
            $service = new BookingRescheduleService();
            $this->previewData = $service->preview(
                $this->bookingDate,
                $this->targetDate,
                $this->selectedSlotIds
            );
        } catch (ValidationException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirmReschedule(): void
    {
        if (!$this->bookingDate || empty($this->selectedSlotIds)) {
            $this->errorMessage = 'Please select at least one slot.';
            return;
        }

        $this->errorMessage = null;

        try {
            $service = new BookingRescheduleService();
            $service->reschedule(
                $this->bookingDate,
                $this->targetDate,
                $this->selectedSlotIds,
                auth()->user(),
                $this->reason
            );

            session()->flash('status', 'Session successfully rescheduled.');
            $this->dispatch('booking-rescheduled');
            $this->dispatch('global-context-updated');
            $this->close();
        } catch (ValidationException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function close(): void
    {
        $this->resetState();
        $this->isOpen = false;
    }

    protected function resetState(): void
    {
        $this->bookingDateId = null;
        $this->bookingDate = null;
        $this->targetDate = '';
        $this->selectedSlotIds = [];
        $this->reason = null;
        $this->availableSlots = [];
        $this->previewData = null;
        $this->errorMessage = null;
        $this->successMessage = null;
    }
}; ?>

<div>
    @if ($isOpen && $bookingDate)
        <div class="fixed inset-0 z-60 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6"
             wire:click.self="close">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-2xl w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150 flex flex-col max-h-[90vh]">
                
                <!-- Modal Header -->
                <div class="px-6 py-4.5 bg-gradient-to-r from-sky-50 to-indigo-50/40 border-b border-slate-200 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-600 flex items-center justify-center font-bold text-lg shrink-0 border border-sky-200/50">
                            🔄
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900">Reschedule Session</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-sky-100 text-sky-800 border border-sky-200">
                                    {{ $reschedulesAllowed - $reschedulesUsed }} left
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Current: <strong class="text-slate-800">{{ Carbon::parse($bookingDate->booking_date)->format('d M Y') }}</strong>
                                ({{ $bookingDate->booking->booking_reference ?? ('#' . $bookingDate->booking_id) }})
                            </p>
                        </div>
                    </div>
                    <button wire:click="close" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-200/50 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="p-6 space-y-5 overflow-y-auto flex-1 text-xs sm:text-sm">
                    @if ($errorMessage)
                        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-3">
                            <span class="text-base shrink-0">⚠️</span>
                            <span>{{ $errorMessage }}</span>
                        </div>
                    @endif

                    <!-- Date Selection -->
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-1.5">
                            Target Date
                        </label>
                        <input type="date" wire:model.live="targetDate" min="{{ Carbon::today('Asia/Kolkata')->toDateString() }}"
                               class="w-full sm:w-64 px-4 py-2.5 rounded-xl border border-slate-200 bg-white font-semibold text-sm text-slate-800 focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 shadow-2xs transition" />
                    </div>

                    <!-- Slots Grid -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-600">
                                Select Available Slots
                            </label>
                            <span class="text-[11px] text-slate-400 font-medium">Click slots to select/unselect</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                            @forelse ($availableSlots as $slot)
                                @php
                                    $isSelected = in_array($slot['id'], $selectedSlotIds);
                                    $isUnavailable = !$slot['is_available'];
                                @endphp
                                <button type="button"
                                        wire:click="toggleSlot({{ $slot['id'] }})"
                                        @if ($isUnavailable) disabled @endif
                                        class="p-2.5 rounded-xl border text-left transition flex flex-col justify-between cursor-pointer disabled:cursor-not-allowed
                                            {{ $isSelected ? 'bg-sky-600 text-white border-sky-600 shadow-sm ring-2 ring-sky-600/30' : ($isUnavailable ? 'bg-slate-50 border-slate-200 opacity-50' : 'bg-white border-slate-200 hover:border-sky-300 hover:bg-sky-50/30') }}">
                                    <div>
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-extrabold text-[11px] {{ $isSelected ? 'text-white' : 'text-slate-800' }}">
                                                {{ $slot['time_label'] }}
                                            </span>
                                            @if ($slot['is_current_slot'])
                                                <span class="text-[9px] font-black px-1 rounded uppercase tracking-wider {{ $isSelected ? 'bg-sky-700 text-white' : 'bg-amber-100 text-amber-800' }}">Current</span>
                                            @elseif ($slot['is_booked'])
                                                <span class="text-[9px] font-bold px-1 rounded bg-slate-200 text-slate-600">Booked</span>
                                            @elseif ($slot['is_locked'])
                                                <span class="text-[9px] font-bold px-1 rounded bg-rose-100 text-rose-700">Locked</span>
                                            @elseif ($slot['is_past'])
                                                <span class="text-[9px] font-bold px-1 rounded bg-slate-200 text-slate-500">Past</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold">
                                        <span class="{{ $isSelected ? 'text-sky-100' : 'text-slate-500' }}">₹{{ number_format($slot['price'], 2) }}</span>
                                        @if ($isSelected)
                                            <span class="text-white text-xs">✓</span>
                                        @endif
                                    </div>
                                </button>
                            @empty
                                <div class="col-span-full p-4 text-center text-xs text-slate-400">
                                    No slots available for this date.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Live Preview Card -->
                    @if ($previewData)
                        <div class="p-4 rounded-2xl bg-gradient-to-br from-slate-50 to-sky-50/40 border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-wider text-slate-700">Reschedule Preview</span>
                                @if ($previewData['settlement_type'] === 'balance_due')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">
                                        +₹{{ number_format($previewData['price_difference'], 2) }} Balance Due
                                    </span>
                                @elseif ($previewData['settlement_type'] === 'refund')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        ₹{{ number_format($previewData['refund_amount'], 2) }} Refund ({{ $previewData['refund_channel'] === 'online_gateway' ? 'Razorpay' : 'Cash' }})
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-700 border border-slate-200">
                                        Same Price (No difference)
                                    </span>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Current Amount</span>
                                    <p class="font-black text-slate-900 mt-0.5">₹{{ number_format($previewData['old_amount'], 2) }}</p>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">New Amount</span>
                                    <p class="font-black text-slate-900 mt-0.5">₹{{ number_format($previewData['new_amount'], 2) }}</p>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Net Paid</span>
                                    <p class="font-black text-emerald-600 mt-0.5">₹{{ number_format($previewData['net_paid'], 2) }}</p>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">New Balance</span>
                                    <p class="font-black {{ $previewData['new_balance_due'] > 0 ? 'text-amber-600' : 'text-slate-700' }} mt-0.5">
                                        ₹{{ number_format($previewData['new_balance_due'], 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Reason Input -->
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-1">
                            Reason for Reschedule (Optional)
                        </label>
                        <input type="text" wire:model="reason" placeholder="e.g. Customer requested weather postponement"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-800 focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 shadow-2xs transition" />
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" wire:click="close"
                            class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmReschedule"
                            wire:loading.attr="disabled"
                            @if (!$previewData || empty($selectedSlotIds)) disabled @endif
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white text-xs font-black uppercase tracking-wider shadow-sm hover:shadow transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="confirmReschedule">Confirm Reschedule</span>
                        <span wire:loading wire:target="confirmReschedule" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Processing...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
