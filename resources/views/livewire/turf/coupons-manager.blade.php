<?php

use App\Models\Turf;
use App\Models\Coupon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $search = '';
    public $showModal = false;
    
    // Form fields
    public $couponId = null;
    public $code = '';
    public $description = '';
    public $discount_type = 'percentage';
    public $discount_value = '';
    public $max_discount_amount = '';
    public $minimum_slots_to_be_ordered = 1;
    public $usage_limit = '';
    public $usage_limit_per_user = 1;
    public $is_active = true;
    
    // Weekdays
    public $mon = true;
    public $tue = true;
    public $wed = true;
    public $thu = true;
    public $fri = true;
    public $sat = true;
    public $sun = true;
    
    // Dates
    public $starts_at = '';
    public $expires_at = '';

    #[On('global-context-updated')]
    public function refreshCoupons()
    {
        $this->resetInputFields();
        $this->showModal = false;
    }

    public function mount()
    {
        // Init
    }

    public function generateCode()
    {
        $this->code = strtoupper(Str::random(8));
    }

    public function resetInputFields()
    {
        $this->couponId = null;
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percentage';
        $this->discount_value = '';
        $this->max_discount_amount = '';
        $this->minimum_slots_to_be_ordered = 1;
        $this->usage_limit = '';
        $this->usage_limit_per_user = 1;
        $this->is_active = true;
        
        $this->mon = true;
        $this->tue = true;
        $this->wed = true;
        $this->thu = true;
        $this->fri = true;
        $this->sat = true;
        $this->sun = true;
        
        $this->starts_at = '';
        $this->expires_at = '';
        
        $this->resetErrorBag();
    }

    public function createCoupon()
    {
        $this->resetInputFields();
        $this->showModal = true;
    }

    public function editCoupon($id)
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) return;

        $coupon = Coupon::where('turf_id', $activeTurfId)->findOrFail($id);
        
        $this->couponId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description;
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = $coupon->discount_value;
        $this->max_discount_amount = $coupon->max_discount_amount;
        $this->minimum_slots_to_be_ordered = $coupon->minimum_slots_to_be_ordered;
        $this->usage_limit = $coupon->usage_limit;
        $this->usage_limit_per_user = $coupon->usage_limit_per_user;
        $this->is_active = (bool)$coupon->is_active;
        
        $this->mon = (bool)$coupon->mon;
        $this->tue = (bool)$coupon->tue;
        $this->wed = (bool)$coupon->wed;
        $this->thu = (bool)$coupon->thu;
        $this->fri = (bool)$coupon->fri;
        $this->sat = (bool)$coupon->sat;
        $this->sun = (bool)$coupon->sun;
        
        $this->starts_at = $coupon->starts_at ? $coupon->starts_at->format('Y-m-d') : '';
        $this->expires_at = $coupon->expires_at ? $coupon->expires_at->format('Y-m-d') : '';
        
        $this->showModal = true;
    }

    public function saveCoupon()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) return;

        $rules = [
            'code' => 'required|string|max:50',
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_slots_to_be_ordered' => 'required|integer|min:1',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_limit_per_user' => 'required|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ];

        $validatedData = $this->validate($rules);

        $data = array_merge($validatedData, [
            'turf_id' => $activeTurfId,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'mon' => $this->mon,
            'tue' => $this->tue,
            'wed' => $this->wed,
            'thu' => $this->thu,
            'fri' => $this->fri,
            'sat' => $this->sat,
            'sun' => $this->sun,
        ]);

        if ($this->couponId) {
            $coupon = Coupon::where('turf_id', $activeTurfId)->findOrFail($this->couponId);
            $coupon->update($data);
            session()->flash('status', 'Coupon updated successfully.');
        } else {
            Coupon::create($data);
            session()->flash('status', 'Coupon created successfully.');
        }

        $this->showModal = false;
        $this->resetInputFields();
    }

    public function toggleStatus($id)
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) return;

        $coupon = Coupon::where('turf_id', $activeTurfId)->findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        session()->flash('status', 'Coupon status updated successfully.');
    }

    public function deleteCoupon($id)
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) return;

        $coupon = Coupon::where('turf_id', $activeTurfId)->findOrFail($id);
        $coupon->delete();
        session()->flash('status', 'Coupon deleted successfully.');
    }

    public function with()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;
        $coupons = collect();

        if ($activeTurfId) {
            $turf = Turf::manageable()->find($activeTurfId);
            if ($turf) {
                $query = Coupon::where('turf_id', $activeTurfId);
                if ($this->search) {
                    $query->where('code', 'like', '%' . $this->search . '%');
                }
                $coupons = $query->orderBy('created_at', 'desc')->get();
            }
        }

        return [
            'turf' => $turf,
            'coupons' => $coupons,
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Status Flash Message -->
        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (!$turf)
            <!-- Unselected Turf Empty State -->
            <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-500 dark:text-amber-400 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 dark:border-amber-950/50">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-750 dark:text-gray-250 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure coupons.') }}</p>
            </div>
        @else
            <!-- Header Section -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Discount Coupons for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage promotional offers, flat discounts, and slot-based coupons for your customers.') }}</p>
                </div>
                <button type="button" wire:click="createCoupon" class="px-5 py-2.5 bg-indigo-650 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Coupon') }}
                </button>
            </div>

            <!-- Coupons Search & Table -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700/50">
                    <div class="relative max-w-md">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search coupon code..." class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700/30 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700/50 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                    </div>
                </div>

                @if ($coupons->isEmpty())
                    <div class="p-16 text-center">
                        <div class="h-14 w-14 rounded-2xl bg-gray-50 dark:bg-gray-750 text-gray-400 flex items-center justify-center mx-auto mb-4 border border-gray-100/50 dark:border-gray-700/50">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-750 dark:text-gray-200 uppercase tracking-wider">{{ __('No Coupons Found') }}</h3>
                        <p class="text-xs text-gray-450 mt-1.5 leading-relaxed">{{ __('No discount coupons match your search or exist for this turf yet.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/50">
                            <thead class="bg-gray-50 dark:bg-gray-750/30">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Code') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Discount') }}</th>
                                    <th scope="col" class="px-6 py-4 class-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Min Slots') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Days Active') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Usage Limit') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Validity') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-4 text-right text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-750/30 bg-white dark:bg-gray-800">
                                @foreach ($coupons as $coupon)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-750/10 transition duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <div class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-700 dark:text-indigo-400 text-xs font-black rounded-lg border border-indigo-100/50 dark:border-indigo-900/30 font-mono tracking-wide">
                                                    {{ $coupon->code }}
                                                </div>
                                                @if ($coupon->description)
                                                    <span class="text-xs text-gray-400 truncate max-w-xs block" title="{{ $coupon->description }}">{{ $coupon->description }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200">
                                                @if ($coupon->discount_type === 'percentage')
                                                    {{ $coupon->discount_value }}%
                                                @else
                                                    ₹{{ $coupon->discount_value }}
                                                @endif
                                            </span>
                                            @if ($coupon->discount_type === 'percentage' && $coupon->max_discount_amount)
                                                <span class="text-[10px] text-gray-400 block mt-0.5">Max ₹{{ $coupon->max_discount_amount }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $coupon->minimum_slots_to_be_ordered }} slot(s)</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-1">
                                                @php $days = ['mon' => 'M', 'tue' => 'T', 'wed' => 'W', 'thu' => 'T', 'fri' => 'F', 'sat' => 'S', 'sun' => 'S']; @endphp
                                                @foreach ($days as $field => $label)
                                                    <span class="text-[9px] w-4.5 h-4.5 rounded-full flex items-center justify-center font-bold {{ $coupon->{$field} ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-650 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/40' : 'bg-gray-50 dark:bg-gray-750 text-gray-300 dark:text-gray-650' }}">
                                                        {{ $label }}
                                                    </span>
                                                @endphp
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">
                                                {{ $coupon->used_count }} / {{ $coupon->usage_limit ?: '∞' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">
                                                @if ($coupon->starts_at || $coupon->expires_at)
                                                    {{ $coupon->starts_at ? $coupon->starts_at->format('M d') : 'Now' }} - 
                                                    {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : 'Never' }}
                                                @else
                                                    {{ __('Always Valid') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button type="button" wire:click="toggleStatus({{ $coupon->id }})" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $coupon->is_active ? 'bg-indigo-600' : 'bg-gray-250 dark:bg-gray-700' }}">
                                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $coupon->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-1">
                                            <button type="button" wire:click="editCoupon({{ $coupon->id }})" class="p-2 bg-gray-50 hover:bg-indigo-50 hover:text-indigo-650 dark:bg-gray-750 dark:hover:bg-indigo-950/30 dark:hover:text-indigo-400 text-gray-600 dark:text-gray-400 rounded-lg transition cursor-pointer inline-flex items-center">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button type="button" wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Are you sure you want to permanently delete this discount coupon?" class="p-2 bg-gray-50 hover:bg-red-50 hover:text-red-650 dark:bg-gray-750 dark:hover:bg-red-950/30 dark:hover:text-red-400 text-gray-600 dark:text-gray-400 rounded-lg transition cursor-pointer inline-flex items-center">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Edit/Create Coupon Modal Dialog overlay -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background backdrop shadow -->
                <div class="fixed inset-0 bg-gray-550 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-80 transition-opacity" wire:click="$set('showModal', false)"></div>

                <!-- Center modal card -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100 dark:border-gray-750">
                    <form wire:submit="saveCoupon" class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        <div class="px-6 py-4.5 bg-gray-50/50 dark:bg-gray-750/30 flex items-center justify-between">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100" id="modal-title">
                                {{ $couponId ? __('Edit Discount Coupon') : __('Add Discount Coupon') }}
                            </h3>
                            <button type="button" wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-650 dark:hover:text-gray-250 cursor-pointer transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Form Input Elements -->
                        <div class="px-6 py-6 space-y-5.5 max-h-[70vh] overflow-y-auto">
                            <!-- Code and Type -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5.5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Coupon Code') }}</label>
                                    <div class="flex gap-2">
                                        <input type="text" wire:model="code" placeholder="e.g. SUMMER50" class="flex-1 px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none uppercase font-mono tracking-wider">
                                        <button type="button" wire:click="generateCode" class="px-3.5 py-2.5 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-750 text-xs font-bold rounded-xl transition cursor-pointer">
                                            {{ __('Generate') }}
                                        </button>
                                    </div>
                                    @error('code') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Discount Type') }}</label>
                                    <select wire:model.live="discount_type" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                        <option value="percentage">{{ __('Percentage (%)') }}</option>
                                        <option value="fixed">{{ __('Fixed Flat Amount (₹)') }}</option>
                                    </select>
                                    @error('discount_type') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Value and Max Discount -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5.5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                        {{ $discount_type === 'percentage' ? __('Percentage Value') : __('Flat Value') }}
                                    </label>
                                    <input type="number" step="0.01" wire:model="discount_value" placeholder="{{ $discount_type === 'percentage' ? 'e.g. 15' : 'e.g. 200' }}" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    @error('discount_value') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Max Discount Amount') }}</label>
                                    <input type="number" step="0.01" wire:model="max_discount_amount" placeholder="{{ $discount_type === 'percentage' ? __('e.g. 500 (Optional)') : __('N/A') }}" {{ $discount_type === 'fixed' ? 'disabled' : '' }} class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none disabled:bg-gray-50 dark:disabled:bg-gray-800 disabled:text-gray-400">
                                    @error('max_discount_amount') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Description') }}</label>
                                <textarea wire:model="description" rows="2" placeholder="Describe the coupon parameters for display..." class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none"></textarea>
                            </div>

                            <!-- Min Slots and User Limit -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5.5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Minimum Slots Booking Needed') }}</label>
                                    <input type="number" wire:model="minimum_slots_to_be_ordered" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    @error('minimum_slots_to_be_ordered') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Limit Per User') }}</label>
                                    <input type="number" wire:model="usage_limit_per_user" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    @error('usage_limit_per_user') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Usage Limit and Validity -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5.5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Total Max Usage') }}</label>
                                    <input type="number" wire:model="usage_limit" placeholder="e.g. 100 (Optional)" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    @error('usage_limit') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Starts At') }}</label>
                                    <input type="date" wire:model="starts_at" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    @error('starts_at') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Expires At') }}</label>
                                    <input type="date" wire:model="expires_at" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-700/20 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    @error('expires_at') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Weekdays Active -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">{{ __('Active Weekdays') }}</label>
                                <div class="flex flex-wrap gap-3">
                                    @foreach (['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $field => $label)
                                        <label class="inline-flex items-center gap-2 cursor-pointer bg-gray-50 dark:bg-gray-750 px-3 py-2 rounded-xl border border-gray-150 dark:border-gray-700">
                                            <input type="checkbox" wire:model="{{ $field }}" class="h-4 w-4 text-indigo-650 border-gray-300 rounded focus:ring-indigo-500">
                                            <span class="text-xs text-gray-700 dark:text-gray-350">{{ __($label) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Active status toggle -->
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-750/30 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Coupon Active Status') }}</h4>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Control if customers can check out using this coupon.') }}</p>
                                </div>
                                <button type="button" wire:click="$toggle('is_active')" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $is_active ? 'bg-indigo-600' : 'bg-gray-250 dark:bg-gray-700' }}">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-750/30 flex items-center justify-end gap-3 rounded-b-3xl">
                            <button type="button" wire:click="$set('showModal', false)" class="px-5 py-2.5 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-750 dark:border-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-650 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition cursor-pointer">
                                {{ __('Save Coupon') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
