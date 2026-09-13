<?php

use App\Models\SubscriptionPackage;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Search & Filter
    public string $search = '';
    public string $statusFilter = 'all';
    public string $sortBy = 'sort_order';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $deletingId = null;

    // Form fields
    public string $name = '';
    public string $description = '';
    public string $monthly_amount = '3000.00';
    public string $yearly_amount = '30000.00';
    public bool $is_active = true;
    public int $sort_order = 0;
    public string $features_text = '';

    // Launch Offer fields
    public bool $is_offer_active = false;
    public string $offer_badge = '🔥 First 100 Turfs Founder Offer';
    public string $offer_monthly_amount = '999.00';
    public string $offer_yearly_amount = '9999.00';
    public ?int $offer_max_claims = 100;
    public int $offer_claimed_count = 0;
    public ?string $offer_expires_at = null;

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id)
    {
        $pkg = SubscriptionPackage::find($id);
        if (!$pkg) return;

        $this->editingId = $pkg->id;
        $this->name = $pkg->name;
        $this->description = $pkg->description ?? '';
        $this->monthly_amount = (string) $pkg->monthly_amount;
        $this->yearly_amount = (string) $pkg->yearly_amount;
        $this->is_active = (bool) $pkg->is_active;
        $this->sort_order = (int) $pkg->sort_order;
        $this->features_text = is_array($pkg->features) ? implode("\n", $pkg->features) : '';

        // Offer fields
        $this->is_offer_active = (bool) $pkg->is_offer_active;
        $this->offer_badge = $pkg->offer_badge ?? '🔥 First 100 Turfs Founder Offer';
        $this->offer_monthly_amount = $pkg->offer_monthly_amount !== null ? (string)$pkg->offer_monthly_amount : '';
        $this->offer_yearly_amount = $pkg->offer_yearly_amount !== null ? (string)$pkg->offer_yearly_amount : '';
        $this->offer_max_claims = $pkg->offer_max_claims;
        $this->offer_claimed_count = (int) $pkg->offer_claimed_count;
        $this->offer_expires_at = $pkg->offer_expires_at ? $pkg->offer_expires_at->format('Y-m-d') : null;

        $this->showFormModal = true;
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->monthly_amount = '3000.00';
        $this->yearly_amount = '30000.00';
        $this->is_active = true;
        $this->sort_order = (int) (SubscriptionPackage::max('sort_order') + 1);
        $this->features_text = '';

        $this->is_offer_active = false;
        $this->offer_badge = '🔥 First 100 Turfs Founder Offer';
        $this->offer_monthly_amount = '999.00';
        $this->offer_yearly_amount = '9999.00';
        $this->offer_max_claims = 100;
        $this->offer_claimed_count = 0;
        $this->offer_expires_at = null;
    }

    public function savePackage()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'monthly_amount' => 'required|numeric|min:0',
            'yearly_amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'is_offer_active' => 'boolean',
            'offer_badge' => 'nullable|string|max:150',
            'offer_monthly_amount' => 'nullable|numeric|min:0',
            'offer_yearly_amount' => 'nullable|numeric|min:0',
            'offer_max_claims' => 'nullable|integer|min:1',
            'offer_expires_at' => 'nullable|date',
        ]);

        $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $this->features_text))));

        $data = [
            'name' => trim($this->name),
            'description' => $this->description ? trim($this->description) : null,
            'monthly_amount' => (float) $this->monthly_amount,
            'yearly_amount' => (float) $this->yearly_amount,
            'commission_percentage' => 0.00,
            'is_active' => $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'features' => $featuresArray,
            'is_offer_active' => $this->is_offer_active,
            'offer_badge' => $this->offer_badge ? trim($this->offer_badge) : null,
            'offer_monthly_amount' => $this->offer_monthly_amount !== '' ? (float) $this->offer_monthly_amount : null,
            'offer_yearly_amount' => $this->offer_yearly_amount !== '' ? (float) $this->offer_yearly_amount : null,
            'offer_max_claims' => $this->offer_max_claims ?: null,
            'offer_expires_at' => $this->offer_expires_at ?: null,
        ];

        if ($this->editingId) {
            $pkg = SubscriptionPackage::findOrFail($this->editingId);
            $pkg->update($data);
            session()->flash('status', __('Subscription Package updated successfully!'));
        } else {
            SubscriptionPackage::create($data);
            session()->flash('status', __('New Subscription Package created successfully!'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id)
    {
        $pkg = SubscriptionPackage::find($id);
        if ($pkg) {
            $pkg->is_active = !$pkg->is_active;
            $pkg->save();
            session()->flash('status', __('Package status updated successfully.'));
        }
    }

    public function toggleOffer(int $id)
    {
        $pkg = SubscriptionPackage::find($id);
        if ($pkg) {
            $pkg->is_offer_active = !$pkg->is_offer_active;
            $pkg->save();
            session()->flash('status', __(':name launch offer :state.', [
                'name' => $pkg->name,
                'state' => $pkg->is_offer_active ? 'enabled' : 'disabled',
            ]));
        }
    }

    public function confirmDelete(int $id)
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deletePackage()
    {
        if ($this->deletingId) {
            $pkg = SubscriptionPackage::find($this->deletingId);
            if ($pkg) {
                $pkg->delete();
                session()->flash('status', __('Subscription Package deleted successfully.'));
            }
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function with(): array
    {
        $query = SubscriptionPackage::query();

        if ($this->search) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('description', 'like', $term)
                  ->orWhere('offer_badge', 'like', $term);
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($this->statusFilter === 'offer_active') {
            $query->where('is_offer_active', true);
        }

        switch ($this->sortBy) {
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'monthly_amount':
                $query->orderBy('monthly_amount', 'asc');
                break;
            case 'sort_order':
            default:
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
                break;
        }

        return [
            'packages' => $query->get(),
        ];
    }
}; ?>

<div class="py-6">
    <div class="w-full sm:px-6 lg:px-8 space-y-6">

        <!-- Top Header Card -->
        <div class="bg-white p-6 sm:p-8 shadow-sm rounded-3xl border border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Subscription Packages & Launch Offers') }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Manage B2B software tiers, standard pricing, and special introductory discounts for turf owners.') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="openCreateModal" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition duration-150 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ __('Create Package') }}</span>
                </button>
            </div>
        </div>

        @if (session()->has('status'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-5 py-3.5 rounded-2xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Filter Bar -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
            <div class="relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="{{ __('Search package name, offer badge...') }}" 
                    class="w-full pl-10 pr-4 py-2 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-medium text-gray-900 transition" />
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <!-- Status Filter -->
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{{ __('Filter:') }}</span>
                    <select wire:model.live="statusFilter" class="py-2 pl-3 pr-8 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-700 transition">
                        <option value="all">{{ __('All Packages') }}</option>
                        <option value="active">{{ __('Active Only') }}</option>
                        <option value="offer_active">{{ __('Active Offers Only') }}</option>
                        <option value="inactive">{{ __('Inactive Only') }}</option>
                    </select>
                </div>

                <!-- Sort Filter -->
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{{ __('Sort:') }}</span>
                    <select wire:model.live="sortBy" class="py-2 pl-3 pr-8 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-700 transition">
                        <option value="sort_order">{{ __('Sort Order') }}</option>
                        <option value="name">{{ __('Package Name') }}</option>
                        <option value="monthly_amount">{{ __('Price: Low to High') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Packages List Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
            @forelse ($packages as $pkg)
                @php
                    $isOffer = $pkg->isOfferValid();
                @endphp
                <div class="bg-white rounded-3xl border {{ $isOffer ? 'border-amber-200 shadow-md ring-1 ring-amber-200' : 'border-gray-100 shadow-sm' }} hover:shadow-lg transition-all duration-300 p-6 flex flex-col justify-between space-y-6 relative overflow-hidden">
                    
                    @if ($isOffer)
                        <div class="absolute -top-1 right-6 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-b-xl shadow-xs flex items-center gap-1">
                            <span>{{ $pkg->offer_badge ?: 'Launch Offer' }}</span>
                        </div>
                    @endif

                    <div class="space-y-4 pt-1">
                        <!-- Top Header Row -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <h3 class="text-lg font-black text-gray-900 leading-snug">
                                    {{ $pkg->name }}
                                </h3>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button wire:click="toggleActive({{ $pkg->id }})" type="button" 
                                        class="cursor-pointer inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider transition {{ $pkg->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                                        {{ $pkg->is_active ? '● Active' : '○ Inactive' }}
                                    </button>

                                    @if ($pkg->is_offer_active)
                                        <button wire:click="toggleOffer({{ $pkg->id }})" type="button"
                                            class="cursor-pointer inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $isOffer ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-gray-100 text-gray-500 border border-gray-200 line-through' }}">
                                            {{ $isOffer ? 'Offer Live' : 'Offer Expired/Full' }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button wire:click="openEditModal({{ $pkg->id }})" type="button" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition cursor-pointer" title="{{ __('Edit') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="confirmDelete({{ $pkg->id }})" type="button" class="p-2 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer" title="{{ __('Delete') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Description -->
                        @if ($pkg->description)
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ $pkg->description }}
                            </p>
                        @endif

                        <!-- Offer Scarcity Quota Banner -->
                        @if ($isOffer && $pkg->offer_max_claims)
                            <div class="bg-amber-50/80 border border-amber-200/80 rounded-2xl p-3 space-y-1.5">
                                <div class="flex items-center justify-between text-[11px] font-bold">
                                    <span class="text-amber-800 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        {{ __('Founder Quota:') }}
                                    </span>
                                    <span class="font-mono text-amber-900 font-extrabold">
                                        {{ $pkg->offer_claimed_count }} / {{ $pkg->offer_max_claims }} {{ __('Claimed') }}
                                    </span>
                                </div>
                                @php
                                    $pct = min(100, round(($pkg->offer_claimed_count / $pkg->offer_max_claims) * 100));
                                @endphp
                                <div class="w-full bg-amber-200/60 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-[10px] text-amber-700 font-semibold block text-right">
                                    {{ $pkg->getRemainingOfferClaims() }} {{ __('spots remaining') }}
                                </span>
                            </div>
                        @endif

                        <!-- Features -->
                        @if (is_array($pkg->features) && count($pkg->features) > 0)
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">{{ __('INCLUDED FEATURES') }}</span>
                                <div class="space-y-1">
                                    @foreach ($pkg->features as $feat)
                                        <div class="text-xs text-gray-700 flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            <span class="break-words font-medium">{{ $feat }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Pricing Box -->
                    <div class="bg-gray-50/80 p-4 rounded-2xl border border-gray-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">{{ __('MONTHLY') }}</span>
                                <div class="flex items-baseline gap-1.5">
                                    @if ($isOffer && $pkg->offer_monthly_amount !== null)
                                        <span class="text-lg font-black text-amber-600 font-mono">₹{{ number_format($pkg->offer_monthly_amount, 2) }}</span>
                                        <span class="text-xs text-gray-400 line-through font-mono">₹{{ number_format($pkg->monthly_amount, 2) }}</span>
                                    @else
                                        <span class="text-lg font-black text-gray-900 font-mono">₹{{ number_format($pkg->monthly_amount, 2) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">{{ __('YEARLY') }}</span>
                                <div class="flex items-baseline justify-end gap-1.5">
                                    @if ($isOffer && $pkg->offer_yearly_amount !== null)
                                        <span class="text-lg font-black text-indigo-600 font-mono">₹{{ number_format($pkg->offer_yearly_amount, 2) }}</span>
                                        <span class="text-xs text-gray-400 line-through font-mono">₹{{ number_format($pkg->yearly_amount, 2) }}</span>
                                    @else
                                        <span class="text-lg font-black text-indigo-600 font-mono">₹{{ number_format($pkg->yearly_amount, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-full bg-white p-12 rounded-3xl border border-gray-100 text-center text-gray-400 space-y-3">
                    <span class="text-4xl block">📦</span>
                    <p class="font-bold text-gray-700">{{ __('No subscription packages found.') }}</p>
                    <button wire:click="openCreateModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold cursor-pointer transition">
                        {{ __('Create First Package') }}
                    </button>
                </div>
            @endforelse
        </div>

    </div>

    <!-- CREATE / EDIT MODAL -->
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div wire:click="$set('showFormModal', false)" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity cursor-pointer"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
                    <form wire:submit="savePackage">
                        <!-- Header -->
                        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">{{ __('SaaS Subscription Management') }}</span>
                                <h3 class="text-base font-bold text-gray-900 mt-0.5">{{ $editingId ? __('Edit Subscription Package') : __('Create New Package') }}</h3>
                            </div>
                            <button wire:click="$set('showFormModal', false)" type="button" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-6">
                            <!-- Package Name -->
                            <div>
                                <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Package Name *') }}</label>
                                <input type="text" wire:model="name" placeholder="e.g. Standard Turf Partner" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-900 transition">
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <!-- Standard Pricing Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Monthly Standard Price (₹) *') }}</label>
                                    <input type="number" step="0.01" wire:model="monthly_amount" placeholder="3000.00" 
                                        class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 transition">
                                    <x-input-error :messages="$errors->get('monthly_amount')" class="mt-1" />
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Yearly Standard Price (₹) *') }}</label>
                                    <input type="number" step="0.01" wire:model="yearly_amount" placeholder="30000.00" 
                                        class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 transition">
                                    <x-input-error :messages="$errors->get('yearly_amount')" class="mt-1" />
                                </div>
                            </div>

                            <!-- LAUNCH OFFER & SCARCITY PRICING CARD -->
                            <div class="bg-gradient-to-br from-amber-50/60 to-orange-50/40 border border-amber-200/80 rounded-3xl p-5 space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-amber-200/60">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-lg">🔥</span>
                                        <div>
                                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-amber-900">{{ __('Launch Offer & Promotional Pricing') }}</h4>
                                            <p class="text-[11px] text-amber-700">{{ __('Offer special introductory price automatically to the first batch of turf owners.') }}</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="is_offer_active" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 shadow-inner"></div>
                                    </label>
                                </div>

                                @if ($is_offer_active)
                                    <div class="space-y-4 pt-1">
                                        <!-- Offer Badge Title -->
                                        <div>
                                            <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Offer Badge Title') }}</label>
                                            <input type="text" wire:model="offer_badge" placeholder="e.g. 🔥 First 100 Turfs Founder Offer" 
                                                class="w-full px-4 py-2 bg-white rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-xs font-bold text-gray-900 transition">
                                            <x-input-error :messages="$errors->get('offer_badge')" class="mt-1" />
                                        </div>

                                        <!-- Offer Prices & Quota -->
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div>
                                                <label class="text-xs font-bold text-amber-900 block mb-1">{{ __('Offer Monthly Price (₹)') }}</label>
                                                <input type="number" step="0.01" wire:model="offer_monthly_amount" placeholder="999.00" 
                                                    class="w-full px-4 py-2 bg-white rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-xs font-bold font-mono text-amber-900 transition">
                                                <x-input-error :messages="$errors->get('offer_monthly_amount')" class="mt-1" />
                                            </div>

                                            <div>
                                                <label class="text-xs font-bold text-amber-900 block mb-1">{{ __('Offer Yearly Price (₹)') }}</label>
                                                <input type="number" step="0.01" wire:model="offer_yearly_amount" placeholder="9999.00" 
                                                    class="w-full px-4 py-2 bg-white rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-xs font-bold font-mono text-amber-900 transition">
                                                <x-input-error :messages="$errors->get('offer_yearly_amount')" class="mt-1" />
                                            </div>

                                            <div>
                                                <label class="text-xs font-bold text-amber-900 block mb-1">{{ __('Max Claims Quota') }}</label>
                                                <input type="number" min="1" wire:model="offer_max_claims" placeholder="100" 
                                                    class="w-full px-4 py-2 bg-white rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-xs font-bold text-amber-900 text-center transition">
                                                <x-input-error :messages="$errors->get('offer_max_claims')" class="mt-1" />
                                            </div>
                                        </div>

                                        <!-- Expiry Date & Stats -->
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                            <div>
                                                <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Offer Expiration Date (Optional)') }}</label>
                                                <input type="date" wire:model="offer_expires_at" 
                                                    class="w-full px-4 py-2 bg-white rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-xs font-semibold text-gray-900 transition">
                                                <x-input-error :messages="$errors->get('offer_expires_at')" class="mt-1" />
                                            </div>
                                            <div class="flex items-center justify-between p-3 bg-white/70 rounded-2xl border border-amber-200/60">
                                                <span class="text-xs font-bold text-gray-600">{{ __('Currently Claimed:') }}</span>
                                                <span class="text-sm font-black font-mono text-amber-800">{{ $offer_claimed_count }} {{ __('Turfs') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Description') }}</label>
                                <textarea wire:model="description" rows="2" placeholder="Brief package summary..." 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-medium text-gray-900 transition"></textarea>
                            </div>

                            <!-- Features List -->
                            <div>
                                <label class="text-xs font-bold text-gray-900 block mb-1">{{ __('Included Features (One per line)') }}</label>
                                <textarea wire:model="features_text" rows="3" placeholder="Single Turf Management&#10;WhatsApp Booking Confirmations&#10;Zero Commission Cap" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-mono text-gray-900 transition"></textarea>
                            </div>

                            <!-- Sort Order & Active Status -->
                            <div class="flex items-center justify-between pt-2">
                                <div class="flex items-center gap-3">
                                    <label class="text-xs font-bold text-gray-900">{{ __('Sort Order:') }}</label>
                                    <input type="number" wire:model="sort_order" min="0" 
                                        class="w-20 px-3 py-1.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-xl border border-gray-200 text-xs font-bold text-gray-900 text-center transition">
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="modal_is_active" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="modal_is_active" class="text-xs font-bold text-gray-800 cursor-pointer">
                                        {{ __('Package is Active') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex items-center justify-end gap-3 rounded-b-3xl">
                            <button wire:click="$set('showFormModal', false)" type="button" class="px-5 py-2.5 bg-white hover:bg-gray-100 text-gray-700 rounded-xl font-bold text-xs uppercase tracking-wider border border-gray-200 transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition cursor-pointer">
                                {{ $editingId ? __('Save Changes') : __('Create Package') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- DELETE CONFIRMATION MODAL -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div wire:click="$set('showDeleteModal', false)" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity cursor-pointer"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100 p-6 sm:p-8 space-y-5">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mx-auto">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="text-center space-y-1.5">
                        <h3 class="text-base font-bold text-gray-900">{{ __('Delete Subscription Package?') }}</h3>
                        <p class="text-xs text-gray-500 leading-relaxed">{{ __('Are you sure you want to delete this subscription package? Turf owners will no longer be able to select this tier.') }}</p>
                    </div>
                    <div class="flex items-center justify-center gap-3 pt-2">
                        <button wire:click="$set('showDeleteModal', false)" type="button" class="w-full px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold text-xs uppercase tracking-wider transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                        <button wire:click="deletePackage" type="button" class="w-full px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition cursor-pointer">
                            {{ __('Yes, Delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
