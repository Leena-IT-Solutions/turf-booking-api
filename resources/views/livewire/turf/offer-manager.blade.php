<?php

use App\Models\Coupon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    // Form fields
    public $couponId = null;
    public $code = '';
    public $description = '';
    public $discount_type = 'percentage';
    public $discount_value = '';
    public $max_discount_amount = '';
    public $minimum_slots_to_be_ordered = '';
    public $usage_limit = '';
    public $usage_limit_per_user = '';
    public $starts_at = '';
    public $expires_at = '';
    public $is_active = true;
    public $mon = true;
    public $tue = true;
    public $wed = true;
    public $thu = true;
    public $fri = true;
    public $sat = true;
    public $sun = true;

    // UI state
    public $showFormModal = false;
    public $search = '';

    public function mount()
    {
        $this->resetForm();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->couponId = null;
        $this->showFormModal = true;
    }

    public function openEditModal($id)
    {
        $this->resetValidation();
        $coupon = Coupon::findOrFail($id);
        $this->couponId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description ?? '';
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = $coupon->discount_value;
        $this->max_discount_amount = $coupon->max_discount_amount ?? '';
        $this->minimum_slots_to_be_ordered = $coupon->minimum_slots_to_be_ordered ?? '';
        $this->usage_limit = $coupon->usage_limit ?? '';
        $this->usage_limit_per_user = $coupon->usage_limit_per_user ?? '';
        $this->starts_at = $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\TH:i') : '';
        $this->expires_at = $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : '';
        $this->is_active = $coupon->is_active;
        $this->mon = (bool)$coupon->mon;
        $this->tue = (bool)$coupon->tue;
        $this->wed = (bool)$coupon->wed;
        $this->thu = (bool)$coupon->thu;
        $this->fri = (bool)$coupon->fri;
        $this->sat = (bool)$coupon->sat;
        $this->sun = (bool)$coupon->sun;

        $this->showFormModal = true;
    }

    public function closeModal()
    {
        $this->showFormModal = false;
    }

    public function resetForm()
    {
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percentage';
        $this->discount_value = '';
        $this->max_discount_amount = '';
        $this->minimum_slots_to_be_ordered = '';
        $this->usage_limit = '';
        $this->usage_limit_per_user = '';
        $this->starts_at = now()->format('Y-m-d\TH:i');
        $this->expires_at = now()->addMonth()->format('Y-m-d\TH:i');
        $this->is_active = true;
        $this->mon = true;
        $this->tue = true;
        $this->wed = true;
        $this->thu = true;
        $this->fri = true;
        $this->sat = true;
        $this->sun = true;
    }

    public function save()
    {
        $rules = [
            'code' => 'required|string|max:255|unique:coupons,code,' . $this->couponId,
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_slots_to_be_ordered' => 'nullable|integer|min:1|max:48',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'required|date',
            'expires_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'mon' => 'boolean',
            'tue' => 'boolean',
            'wed' => 'boolean',
            'thu' => 'boolean',
            'fri' => 'boolean',
            'sat' => 'boolean',
            'sun' => 'boolean',
        ];

        if ($this->discount_type === 'percentage') {
            $rules['discount_value'] .= '|max:100';
        }

        $validated = $this->validate($rules);

        // Convert empty strings to null for nullable fields
        foreach (['max_discount_amount', 'minimum_slots_to_be_ordered', 'usage_limit', 'usage_limit_per_user', 'description'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->couponId) {
            Coupon::findOrFail($this->couponId)->update($validated);
            session()->flash('status', 'Coupon updated successfully.');
        } else {
            Coupon::create($validated);
            session()->flash('status', 'Coupon created successfully.');
        }

        $this->closeModal();
    }

    public function toggleActive($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        session()->flash('status', 'Coupon status updated successfully.');
    }

    public function deleteCoupon($id)
    {
        Coupon::findOrFail($id)->delete();
        session()->flash('status', 'Coupon deleted successfully.');
    }

    public function with()
    {
        return [
            'coupons' => Coupon::where('code', 'like', '%' . $this->search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Header Card -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Offers & Discounts') }}</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Setup active discount coupon codes, percentage/flat discounts, and weekday eligibility.') }}</p>
            </div>
            <button wire:click="openCreateModal" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs tracking-wider uppercase transition flex items-center gap-2 shadow-sm shadow-indigo-100 dark:shadow-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Create Coupon') }}
            </button>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-col md:flex-row gap-4 justify-between items-stretch">
            <div class="relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:border-transparent placeholder-gray-400 transition" placeholder="Search coupon code...">
            </div>
        </div>

        <!-- Coupon Cards Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @forelse ($coupons as $coupon)
                <div wire:key="coupon-{{ $coupon->id }}" class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col justify-between relative overflow-hidden transition hover:shadow-md">
                    <!-- Top Ribbon for Status -->
                    <div class="absolute top-0 right-0">
                        @if ($coupon->expires_at->isPast())
                            <span class="inline-flex items-center px-3 py-1.5 rounded-bl-2xl bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 text-[10px] font-bold uppercase tracking-wider border-l border-b border-rose-100 dark:border-rose-950/50">{{ __('Expired') }}</span>
                        @elseif (!$coupon->is_active)
                            <span class="inline-flex items-center px-3 py-1.5 rounded-bl-2xl bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider border-l border-b border-gray-100 dark:border-gray-700">{{ __('Inactive') }}</span>
                        @elseif ($coupon->starts_at->isFuture())
                            <span class="inline-flex items-center px-3 py-1.5 rounded-bl-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold uppercase tracking-wider border-l border-b border-amber-100/50 dark:border-amber-950/50">{{ __('Upcoming') }}</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1.5 rounded-bl-2xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold uppercase tracking-wider border-l border-b border-emerald-100 dark:border-emerald-950/40">{{ __('Active') }}</span>
                        @endif
                    </div>

                    <div>
                        <!-- Coupon Code and Description -->
                        <div class="flex items-center gap-2.5">
                            <span class="flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 shrink-0">
                                🎟️
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 tracking-wide text-sm">{{ $coupon->code }}</h3>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ $coupon->description ?: __('No description') }}</p>
                            </div>
                        </div>

                        <!-- Stats and Conditions Grid -->
                        <div class="grid grid-cols-2 gap-4 mt-6 pt-5 border-t border-gray-50 dark:border-gray-700/40">
                            <div>
                                <span class="block text-[9px] uppercase tracking-wider font-bold text-gray-400 dark:text-gray-500">{{ __('Discount') }}</span>
                                <span class="block text-xs font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">
                                    @if ($coupon->discount_type === 'percentage')
                                        {{ number_format($coupon->discount_value, 0) }}% {{ __('OFF') }}
                                        @if ($coupon->max_discount_amount)
                                            <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500">({{ __('Max') }} ₹{{ number_format($coupon->max_discount_amount, 0) }})</span>
                                        @endif
                                    @else
                                        ₹{{ number_format($coupon->discount_value, 0) }} {{ __('OFF') }}
                                    @endif
                                </span>
                            </div>

                            <div>
                                <span class="block text-[9px] uppercase tracking-wider font-bold text-gray-400 dark:text-gray-500">{{ __('Minimum Slots Required') }}</span>
                                <span class="block text-xs font-bold text-gray-700 dark:text-gray-300 mt-1">
                                    {{ $coupon->minimum_slots_to_be_ordered ?: __('None') }}
                                </span>
                            </div>

                            <div>
                                <span class="block text-[9px] uppercase tracking-wider font-bold text-gray-400 dark:text-gray-500">{{ __('Usage Count') }}</span>
                                <span class="block text-xs font-bold text-gray-700 dark:text-gray-300 mt-1">
                                    {{ $coupon->used_count }} @if($coupon->usage_limit) / {{ $coupon->usage_limit }} @endif
                                </span>
                            </div>

                            <div>
                                <span class="block text-[9px] uppercase tracking-wider font-bold text-gray-400 dark:text-gray-500">{{ __('Expires At') }}</span>
                                <span class="block text-xs font-bold text-gray-700 dark:text-gray-300 mt-1 font-mono">
                                    {{ $coupon->expires_at->format('Y-m-d h:i A') }}
                                </span>
                            </div>
                        </div>

                        <!-- Days Checklist Badge Row -->
                        <div class="mt-4 flex flex-wrap gap-1">
                            @php
                                $daysMapping = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
                                $activeDays = [];
                                foreach ($daysMapping as $key => $label) {
                                    if ($coupon->$key) {
                                        $activeDays[] = $label;
                                    }
                                }
                            @endphp
                            @if (count($activeDays) === 7)
                                <span class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 text-[9px] font-bold border border-emerald-100/50 dark:border-emerald-950/30">{{ __('All Days') }}</span>
                            @elseif (count($activeDays) === 0)
                                <span class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 text-[9px] font-bold border border-rose-100/50 dark:border-rose-950/30">{{ __('No Days') }}</span>
                            @else
                                @foreach ($activeDays as $day)
                                    <span class="px-1.5 py-0.5 rounded bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700 text-[8px] font-bold text-gray-500 dark:text-gray-400">{{ $day }}</span>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-50 dark:border-gray-700/40">
                        <button wire:click="toggleActive({{ $coupon->id }})" class="px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-gray-200 dark:border-gray-700/80 hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-300 transition">
                            {{ $coupon->is_active ? __('Deactivate') : __('Activate') }}
                        </button>
                        <div class="flex gap-2">
                            <button wire:click="openEditModal({{ $coupon->id }})" class="px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-950/30 dark:hover:bg-indigo-900/50 dark:text-indigo-400 transition">
                                {{ __('Edit') }}
                            </button>
                            <button wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Are you sure you want to delete this coupon?" class="px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-950/20 dark:hover:bg-rose-950/40 dark:text-rose-400 transition">
                                {{ __('Delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                    <div class="h-16 w-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/50 dark:border-indigo-950/50">
                        🎟️
                    </div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Coupons Found') }}</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('There are no offers or discount coupons matching your criteria. Click create coupon to get started.') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $coupons->links() }}
        </div>

        <!-- Edit/Create Modal (Standard Tailwind/Vant CSS layout overlay) -->
        @if ($showFormModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm overflow-y-auto">
                <div class="bg-white dark:bg-gray-800 rounded-3xl max-w-2xl w-full border border-gray-100 dark:border-gray-700/50 shadow-2xl overflow-hidden flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-50 dark:border-gray-700/40">
                        <h3 class="font-bold text-gray-950 dark:text-gray-50 text-sm uppercase tracking-wide">
                            {{ $couponId ? __('Edit Coupon') : __('Create New Coupon') }}
                        </h3>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit.prevent="save" class="p-6 space-y-5 overflow-y-auto max-h-[70vh] custom-scrollbar pr-3">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Code -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Coupon Code') }}</label>
                                <input wire:model="code" type="text" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 uppercase" placeholder="e.g. SUMMER50">
                                @error('code') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Discount Type -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Discount Type') }}</label>
                                <select wire:model.live="discount_type" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="percentage">{{ __('Percentage (%)') }}</option>
                                    <option value="fixed">{{ __('Fixed Amount (₹)') }}</option>
                                </select>
                                @error('discount_type') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Discount Value -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Discount Value') }}</label>
                                <div class="relative">
                                    <input wire:model="discount_value" type="number" step="0.01" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500" placeholder="0.00">
                                    <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">
                                        {{ $discount_type === 'percentage' ? '%' : '₹' }}
                                    </span>
                                </div>
                                @error('discount_value') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Max Discount Amount -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Max Discount Amount') }}</label>
                                <div class="relative">
                                    <input wire:model="max_discount_amount" type="number" step="0.01" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional">
                                    <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">₹</span>
                                </div>
                                @error('max_discount_amount') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Description -->
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Description') }}</label>
                                <textarea wire:model="description" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500" rows="2" placeholder="Describe the offer..."></textarea>
                                @error('description') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Minimum Slots -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Minimum Slots Required') }}</label>
                                <select wire:model="minimum_slots_to_be_ordered" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">{{ __('Optional') }}</option>
                                    @for ($i = 1; $i <= 48; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                                @error('minimum_slots_to_be_ordered') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Total Usage Limit -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Overall Usage Limit') }}</label>
                                <input wire:model="usage_limit" type="number" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional">
                                @error('usage_limit') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Usage Limit Per User -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Usage Limit Per User') }}</label>
                                <input wire:model="usage_limit_per_user" type="number" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional">
                                @error('usage_limit_per_user') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Starts At -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Starts At') }}</label>
                                <input wire:model="starts_at" type="datetime-local" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                @error('starts_at') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Expires At -->
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Expires At') }}</label>
                                <input wire:model="expires_at" type="datetime-local" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                @error('expires_at') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Active Status -->
                            <div class="col-span-2">
                                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Active Status') }}</label>
                                        <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('If disabled, this coupon cannot be applied anywhere.') }}</span>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>
                            </div>

                            <!-- Applicable Days -->
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">{{ __('Applicable Days of Week') }}</label>
                                <div class="grid grid-cols-7 gap-2 p-3.5 bg-gray-50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-700/50 rounded-2xl">
                                    @foreach(['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $key => $label)
                                        <label for="day-{{ $key }}" class="flex flex-col items-center gap-1 cursor-pointer">
                                            <span class="text-[9px] font-bold uppercase text-gray-400 dark:text-gray-500">{{ $label }}</span>
                                            <input type="checkbox" id="day-{{ $key }}" wire:model="{{ $key }}" class="rounded text-indigo-600 focus:ring-indigo-500 h-4.5 w-4.5 border-gray-300 bg-white dark:bg-gray-800">
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-50 dark:border-gray-700/40">
                            <button type="button" wire:click="closeModal" class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 transition">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs tracking-wider uppercase transition shadow-sm">
                                {{ $couponId ? __('Update Coupon') : __('Create Coupon') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</div>
