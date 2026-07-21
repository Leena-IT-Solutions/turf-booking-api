<?php

use App\Models\Turf;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\SlotLock;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $selectedDate = '';
    public array $selectedSlotIds = [];
    public string $lockReason = 'Maintenance';
    public array $currentlyLockedSlotIds = [];
    public array $lockReasonsMap = [];

    #[On('global-context-updated')]
    public function refreshContext()
    {
        $this->loadLocks();
    }

    public function mount()
    {
        $this->selectedDate = Carbon::today('Asia/Kolkata')->toDateString();
        $this->loadLocks();
    }

    public function updatedSelectedDate()
    {
        $this->loadLocks();
    }

    public function loadLocks()
    {
        $this->selectedSlotIds = [];
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId || !$this->selectedDate) {
            $this->currentlyLockedSlotIds = [];
            $this->lockReasonsMap = [];
            return;
        }

        $turf = Turf::manageable()->find($activeTurfId);
        if (!$turf) {
            $this->currentlyLockedSlotIds = [];
            $this->lockReasonsMap = [];
            return;
        }

        $locks = SlotLock::where('turf_id', $turf->id)
            ->where('lock_date', $this->selectedDate)
            ->get();

        $this->currentlyLockedSlotIds = $locks->pluck('slot_id')->map(fn($id) => (string)$id)->toArray();
        
        $map = [];
        foreach ($locks as $lock) {
            $map[(string)$lock->slot_id] = [
                'id' => $lock->id,
                'reason' => $lock->reason ?? 'Maintenance',
            ];
        }
        $this->lockReasonsMap = $map;
    }

    public function lockSelectedSlots()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId || !$this->selectedDate || empty($this->selectedSlotIds)) {
            session()->flash('error', 'Please select at least one slot and a valid date.');
            return;
        }

        $turf = Turf::manageable()->findOrFail($activeTurfId);
        $userId = auth()->id();

        foreach ($this->selectedSlotIds as $slotId) {
            SlotLock::updateOrCreate([
                'turf_id' => $turf->id,
                'slot_id' => $slotId,
                'lock_date' => $this->selectedDate,
            ], [
                'reason' => $this->lockReason ?: 'Maintenance',
                'created_by_user_id' => $userId,
            ]);
        }

        $this->loadLocks();
        session()->flash('status', count($this->selectedSlotIds) . ' slot(s) locked successfully for ' . Carbon::parse($this->selectedDate)->format('M d, Y') . '.');
    }

    public function unlockSelectedSlots()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId || !$this->selectedDate || empty($this->selectedSlotIds)) {
            session()->flash('error', 'Please select at least one slot to unlock.');
            return;
        }

        $turf = Turf::manageable()->findOrFail($activeTurfId);

        SlotLock::where('turf_id', $turf->id)
            ->where('lock_date', $this->selectedDate)
            ->whereIn('slot_id', $this->selectedSlotIds)
            ->delete();

        $this->loadLocks();
        session()->flash('status', count($this->selectedSlotIds) . ' slot(s) unlocked successfully.');
    }

    public function unlockSingleSlot($slotId)
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId || !$this->selectedDate) return;

        $turf = Turf::manageable()->findOrFail($activeTurfId);

        SlotLock::where('turf_id', $turf->id)
            ->where('lock_date', $this->selectedDate)
            ->where('slot_id', $slotId)
            ->delete();

        $this->loadLocks();
        session()->flash('status', 'Slot unlocked successfully.');
    }

    public function selectAll()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) return;

        $turf = Turf::manageable()->find($activeTurfId);
        if (!$turf) return;

        $ids = $turf->slots()
            ->wherePivot('is_active', true)
            ->pluck('slots.id')
            ->map(fn($id) => (string)$id)
            ->toArray();
        sort($ids);
        $this->selectedSlotIds = $ids;
    }

    public function deselectAll()
    {
        $this->selectedSlotIds = [];
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Slot Lock & Maintenance Manager
                </h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Lock slots on specific dates for maintenance, ground repair, or private events to block customer bookings.
                </p>
            </div>
        </div>

        <!-- Notification Alerts -->
        @if (session('status'))
            <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/40 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 text-sm">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @php
            $activeTurfId = session('active_turf_id');
            $turf = $activeTurfId ? Turf::manageable()->find($activeTurfId) : null;
        @endphp

        @if (!$turf)
            <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <p class="text-gray-600 dark:text-gray-400">Please select an active Turf from the header dropdown to manage slot locks.</p>
            </div>
        @else
            <!-- Control Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                    <!-- Date Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Select Lock Date
                        </label>
                        <input type="date" wire:model.live="selectedDate" 
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <!-- Lock Reason -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Lock Reason
                        </label>
                        <select wire:model="lockReason" 
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-amber-500 focus:ring-amber-500">
                            <option value="Maintenance">Maintenance / Ground Repair</option>
                            <option value="Private Event">Private Tournament / Event</option>
                            <option value="Rain Delay">Rain / Weather Delay</option>
                            <option value="Staff Hold">Staff / Owner Hold</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <button wire:click="lockSelectedSlots" 
                            class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg text-sm transition flex items-center justify-center gap-1 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Lock Selected
                        </button>
                        <button wire:click="unlockSelectedSlots" 
                            class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-sm transition flex items-center justify-center gap-1 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            Unlock Selected
                        </button>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-sm">
                    <div class="flex gap-2">
                        <button wire:click="selectAll" type="button" class="text-xs text-amber-600 hover:text-amber-700 dark:text-amber-400 font-medium">Select All</button>
                        <span class="text-gray-300 dark:text-gray-600">|</span>
                        <button wire:click="deselectAll" type="button" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">Deselect All</button>
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">
                        Selected: <span class="font-bold text-amber-600 dark:text-amber-400">{{ count($selectedSlotIds) }}</span> | 
                        Currently Locked: <span class="font-bold text-red-600 dark:text-red-400">{{ count($currentlyLockedSlotIds) }}</span>
                    </div>
                </div>
            </div>

            <!-- Slots Grid -->
            @php
                $categories = SlotCategory::with(['slots' => function($q) use ($turf) {
                    $q->whereHas('turfs', function($tq) use ($turf) {
                        $tq->where('turfs.id', $turf->id)->where('is_active', true);
                    })->where('is_active', true);
                }])->get();
            @endphp

            <div class="space-y-6">
                @foreach ($categories as $cat)
                    @if ($cat->slots->isNotEmpty())
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-100 dark:border-gray-700">
                                {{ $cat->name }}
                            </h3>

                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                @foreach ($cat->slots as $slot)
                                    @php
                                        $slotIdStr = (string)$slot->id;
                                        $isLocked = in_array($slotIdStr, $currentlyLockedSlotIds);
                                        $isSelected = in_array($slotIdStr, $selectedSlotIds);
                                        $reason = $isLocked ? ($lockReasonsMap[$slotIdStr]['reason'] ?? 'Locked') : null;
                                        $fromFormatted = date('h:i A', strtotime($slot->from_time));
                                        $toFormatted = date('h:i A', strtotime($slot->to_time));
                                    @endphp

                                    <label class="relative flex flex-col p-3 rounded-lg border cursor-pointer transition select-none {{ $isLocked ? 'bg-red-50 dark:bg-red-950/40 border-red-300 dark:border-red-800' : ($isSelected ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-500 dark:border-amber-500' : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:border-gray-300') }}">
                                        <div class="flex items-center justify-between mb-1">
                                            <input type="checkbox" value="{{ $slot->id }}" wire:model.live="selectedSlotIds" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                            @if ($isLocked)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                    Locked
                                                </span>
                                            @endif
                                        </div>

                                        <span class="text-xs font-bold text-gray-900 dark:text-white">
                                            {{ $fromFormatted }} - {{ $toFormatted }}
                                        </span>

                                        @if ($isLocked)
                                            <span class="text-[10px] text-red-600 dark:text-red-400 mt-1 truncate font-medium" title="{{ $reason }}">
                                                📌 {{ $reason }}
                                            </span>
                                            <button type="button" wire:click.stop="unlockSingleSlot({{ $slot->id }})" class="mt-2 text-[10px] font-semibold text-emerald-600 hover:underline text-left">
                                                🔓 Unlock
                                            </button>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
