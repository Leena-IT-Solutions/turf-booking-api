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
    public $usage_limit_per_user = '';
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
        $this->usage_limit_per_user = '';
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
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ];

        $validatedData = $this->validate($rules);

        $data = array_merge($validatedData, [
            'turf_id' => $activeTurfId,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
            'mon' => $this->mon,
            'tue' => $this->tue,
            'wed' => $this->wed,
            'thu' => $this->thu,
            'fri' => $this->fri,
            'sat' => $this->sat,
            'sun' => $this->sun,
        ]);

        foreach (['max_discount_amount', 'usage_limit', 'usage_limit_per_user', 'starts_at', 'expires_at'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

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
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure coupons.') }}</p>
            </div>
        @else
            <!-- Header Section -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Discount Coupons for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage promotional offers, flat discounts, and slot-based coupons for your customers.') }}</p>
                </div>
                <button type="button" wire:click="createCoupon" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
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
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 dark:text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search coupon code..." class="block w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-750 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out">
                    </div>
                </div>

                @if ($coupons->isEmpty())
                    <div class="p-16 text-center flex flex-col items-center justify-center">
                        <div class="h-16 w-16 rounded-2xl bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-550 flex items-center justify-center mb-4 border border-gray-150 dark:border-gray-750/50">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">{{ __('No Coupons Found') }}</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5 leading-relaxed">{{ __('No discount coupons match your search or exist for this turf yet.') }}</p>
                    </div>
                @else
                    <div class="p-6 space-y-6">
                        @foreach ($coupons as $coupon)
                            @php
                                $isActive = $coupon->is_active;
                                $isPct    = $coupon->discount_type === 'percentage';
                                $discountValue = $isPct ? $coupon->discount_value . '%' : '₹' . number_format($coupon->discount_value, 0);
                            @endphp                            {{-- Full-width horizontal ticket / voucher --}}
                            <div class="flex items-stretch rounded-3xl border border-gray-150 dark:border-gray-800 bg-white dark:bg-gray-800 shadow-xs hover:shadow-sm transition-all duration-200 relative {{ $isActive ? '' : 'opacity-60' }}" style="max-width: 1000px; margin: 0 auto;">

                                {{-- LEFT: coloured discount panel --}}
                                <div class="flex flex-col items-center justify-center py-6 text-center select-none relative {{ $isActive ? 'bg-gradient-to-br from-indigo-500 to-indigo-700' : 'bg-gray-300 dark:bg-gray-600' }} rounded-l-[22px]" style="width: 130px; flex-shrink: 0; border-right: 2px dashed rgba(255,255,255,0.25);">
                                    <span class="text-[9px] font-black uppercase tracking-[0.2em] {{ $isActive ? 'text-indigo-100/90' : 'text-gray-100/80' }}">
                                        {{ $isPct ? __('SAVE') : __('FLAT') }}
                                    </span>
                                    <span class="text-3xl font-black text-white dark:text-gray-100 my-1 font-mono tracking-tight">
                                        {{ $discountValue }}
                                    </span>
                                    <span class="text-[9px] font-black uppercase tracking-[0.2em] {{ $isActive ? 'text-indigo-100/90' : 'text-gray-100/80' }}">
                                        {{ __('OFF') }}
                                    </span>
                                    @if ($isPct && $coupon->max_discount_amount)
                                        <span class="text-[8px] font-black {{ $isActive ? 'text-indigo-200/90' : 'text-gray-200/80' }} mt-2 max-w-[90%] truncate">
                                            {{ __('MAX ₹:amount', ['amount' => number_format($coupon->max_discount_amount, 0)]) }}
                                        </span>
                                    @endif
                                </div>

                                {{-- RIGHT: coupon details --}}
                                <div class="flex-grow flex flex-col justify-between p-6 min-w-0 bg-white dark:bg-gray-800 rounded-r-[22px]">
                                    
                                    {{-- Row 1: code + type + active toggle --}}
                                    <div class="flex items-center justify-between gap-4 mb-4">
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span class="inline-flex items-center px-3 py-1 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-650 dark:text-indigo-400 text-xs font-black rounded-lg border border-indigo-100/40 dark:border-indigo-900/30 font-mono tracking-wider uppercase">
                                                {{ $coupon->code }}
                                            </span>
                                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                                {{ $isPct ? __('Percentage Coupon') : __('Flat Rate Discount') }}
                                            </span>
                                        </div>

                                        {{-- Toggle active status --}}
                                        <button type="button" wire:click="toggleStatus({{ $coupon->id }})" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $isActive ? 'bg-indigo-600' : 'bg-gray-250 dark:bg-gray-700' }}">
                                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isActive ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                        </button>
                                    </div>

                                    {{-- Row 2: description --}}
                                    @if ($coupon->description)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-4">
                                            {{ $coupon->description }}
                                        </p>
                                    @endif

                                    {{-- Row 3: details row --}}
                                    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 16px;" class="text-[10px] uppercase font-bold tracking-wider text-gray-450 dark:text-gray-500">
                                        <div style="min-width: 100px; flex: 1;">
                                            <span class="block font-black mb-1 text-gray-400 dark:text-gray-500">{{ __('Min Slots') }}</span>
                                            <span class="text-xs font-black text-gray-700 dark:text-gray-205">{{ $coupon->minimum_slots_to_be_ordered }} slot(s)</span>
                                        </div>
                                        <div style="min-width: 100px; flex: 1;">
                                            <span class="block font-black mb-1 text-gray-400 dark:text-gray-500">{{ __('Total Limit') }}</span>
                                            <span class="text-xs font-black text-gray-700 dark:text-gray-205 font-mono">
                                                {{ $coupon->used_count }} / {{ $coupon->usage_limit ?: '∞' }}
                                            </span>
                                        </div>
                                        <div style="min-width: 100px; flex: 1;">
                                            <span class="block font-black mb-1 text-gray-400 dark:text-gray-500">{{ __('Limit Per User') }}</span>
                                            <span class="text-xs font-black text-gray-700 dark:text-gray-205 font-mono">
                                                {{ $coupon->usage_limit_per_user ?: '∞' }}
                                            </span>
                                        </div>
                                        <div style="min-width: 150px; flex: 1.5;">
                                            <span class="block font-black mb-1 text-gray-400 dark:text-gray-500">{{ __('Validity') }}</span>
                                            <span class="text-xs font-black text-gray-700 dark:text-gray-205 font-mono">
                                                @if ($coupon->starts_at || $coupon->expires_at)
                                                    {{ $coupon->starts_at ? $coupon->starts_at->format('M d, Y') : 'Now' }} - 
                                                    {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : 'Never' }}
                                                @else
                                                    {{ __('Always Valid') }}
                                                @endif
                                            </span>
                                        </div>
                                        <div style="min-width: 130px; flex: 1.5;">
                                            <span class="block font-black mb-1 text-gray-400 dark:text-gray-500">{{ __('Active Days') }}</span>
                                            <div class="flex items-center gap-1 mt-0.5">
                                                @php $days = ['mon' => 'M', 'tue' => 'T', 'wed' => 'W', 'thu' => 'T', 'fri' => 'F', 'sat' => 'S', 'sun' => 'S']; @endphp
                                                @foreach ($days as $field => $label)
                                                    <span class="text-[9px] w-5 h-5 rounded-full flex items-center justify-center font-bold {{ $coupon->{$field} ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-100/40 dark:border-indigo-900/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-300 dark:text-gray-600' }}">
                                                        {{ $label }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Row 4: action buttons --}}
                                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-150 dark:border-gray-700/50">
                                        <button type="button" wire:click="editCoupon({{ $coupon->id }})" class="px-4 py-2 bg-white dark:bg-gray-800 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/20 dark:hover:text-indigo-400 border border-gray-250 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-xs font-bold uppercase rounded-xl transition cursor-pointer flex items-center gap-2">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                            {{ __('Edit') }}
                                        </button>
                                        <button type="button" wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Are you sure you want to permanently delete this discount coupon?" class="px-4 py-2 bg-white dark:bg-gray-800 hover:bg-red-50 hover:text-red-650 dark:hover:bg-red-950/20 dark:hover:text-red-400 border border-gray-250 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-xs font-bold uppercase rounded-xl transition cursor-pointer flex items-center gap-2">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            {{ __('Delete') }}
                                        </button>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Edit/Create Coupon Modal Dialog overlay -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Background backdrop shadow -->
            <div class="fixed inset-0 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-xs transition-opacity" wire:click="resetInputFields"></div>

            <!-- Center modal card -->
            <div class="relative bg-white dark:bg-gray-800 rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-2xl sm:w-full border border-gray-100 dark:border-gray-700/50 z-10">
                <form wire:submit="saveCoupon" class="divide-y divide-gray-150 dark:divide-gray-700/50">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/40 flex items-center justify-between">
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
                    <div class="px-6 py-6 space-y-6 max-h-[70vh] overflow-y-auto bg-white dark:bg-gray-800">
                        <!-- Code and Type -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Coupon Code') }}</label>
                                <div class="flex gap-2">
                                    <input type="text" wire:model="code" placeholder="e.g. SUMMER50" class="flex-1 px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none uppercase font-mono tracking-wider">
                                    <button type="button" wire:click="generateCode" class="px-3.5 py-2.5 bg-gray-50 dark:bg-gray-900/50 border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900 text-xs font-bold rounded-xl transition cursor-pointer">
                                        {{ __('Generate') }}
                                    </button>
                                </div>
                                @error('code') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Discount Type') }}</label>
                                <select wire:model.live="discount_type" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                    <option value="percentage" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">{{ __('Percentage (%)') }}</option>
                                    <option value="fixed" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">{{ __('Fixed Flat Amount (₹)') }}</option>
                                </select>
                                @error('discount_type') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Value and Max Discount -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">
                                    {{ $discount_type === 'percentage' ? __('Percentage Value') : __('Flat Value') }}
                                </label>
                                <input type="number" step="0.01" wire:model="discount_value" placeholder="{{ $discount_type === 'percentage' ? 'e.g. 15' : 'e.g. 200' }}" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                @error('discount_value') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Max Discount Amount') }}</label>
                                <input type="number" step="0.01" wire:model="max_discount_amount" placeholder="{{ $discount_type === 'percentage' ? 'e.g. 500 (Optional)' : 'N/A' }}" {{ $discount_type === 'fixed' ? 'disabled' : '' }} class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none disabled:bg-gray-50 dark:disabled:bg-gray-900/50 disabled:text-gray-400">
                                @error('max_discount_amount') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Description') }}</label>
                            <textarea wire:model="description" rows="2" placeholder="Describe the coupon parameters for display..." class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none"></textarea>
                        </div>

                        <!-- Min Slots and User Limit -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Minimum Slots Booking Needed') }}</label>
                                <input type="number" wire:model="minimum_slots_to_be_ordered" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                @error('minimum_slots_to_be_ordered') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Limit Per User') }}</label>
                                <input type="number" wire:model="usage_limit_per_user" placeholder="e.g. 1 (Optional)" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                @error('usage_limit_per_user') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Usage Limit and Validity -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Total Max Usage') }}</label>
                                <input type="number" wire:model="usage_limit" placeholder="e.g. 100 (Optional)" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                @error('usage_limit') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Starts At') }}</label>
                                <input type="date" wire:model="starts_at" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                @error('starts_at') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Expires At') }}</label>
                                <input type="date" wire:model="expires_at" class="w-full px-3.5 py-2.5 bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 outline-none">
                                @error('expires_at') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Weekdays Active -->
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ __('Active Weekdays') }}</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach (['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $field => $label)
                                    <label class="inline-flex items-center gap-2 cursor-pointer bg-gray-50 dark:bg-gray-900 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700">
                                        <input type="checkbox" wire:model="{{ $field }}" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        <span class="text-xs text-gray-700 dark:text-gray-300 ml-1">{{ __($label) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Active status toggle -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-150 dark:border-gray-700/50">
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Coupon Active Status') }}</h4>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">{{ __('Control if customers can check out using this coupon.') }}</p>
                            </div>
                            <button type="button" wire:click="$toggle('is_active')" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $is_active ? 'bg-indigo-600' : 'bg-gray-250 dark:bg-gray-700' }}">
                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/40 flex items-center justify-end gap-3 rounded-b-3xl">
                        <button type="button" wire:click="$set('showModal', false)" class="px-5 py-2.5 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-750 dark:border-gray-750 dark:text-gray-300 text-xs font-bold rounded-xl transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition cursor-pointer">
                            {{ __('Save Coupon') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
