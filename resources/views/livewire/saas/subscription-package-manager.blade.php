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
    public string $amount = '';
    public int $days = 30;
    public string $total_percentage = '0.00';
    public string $payment_gateway_percentage = '0.00';
    public string $commission_percentage = '0.00';
    public bool $is_active = true;
    public string $from_date = '';
    public string $to_date = '';
    public int $sort_order = 0;
    public string $features_text = '';

    public function updatedPaymentGatewayPercentage()
    {
        $this->recalculateTotalPercentage();
    }

    public function updatedCommissionPercentage()
    {
        $this->recalculateTotalPercentage();
    }

    private function recalculateTotalPercentage()
    {
        $gw = (float) $this->payment_gateway_percentage;
        $comm = (float) $this->commission_percentage;
        $this->total_percentage = number_format($gw + $comm, 2, '.', '');
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->from_date = Carbon::now('Asia/Kolkata')->toDateString();
        $this->to_date = Carbon::now('Asia/Kolkata')->addYear()->toDateString();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id)
    {
        $pkg = SubscriptionPackage::find($id);
        if (!$pkg) return;

        $this->editingId = $pkg->id;
        $this->name = $pkg->name;
        $this->description = $pkg->description ?? '';
        $this->amount = (string) $pkg->amount;
        $this->days = (int) $pkg->days;
        $this->total_percentage = (string) $pkg->total_percentage;
        $this->payment_gateway_percentage = (string) $pkg->payment_gateway_percentage;
        $this->commission_percentage = (string) $pkg->commission_percentage;
        $this->is_active = (bool) $pkg->is_active;
        $this->from_date = $pkg->from_date ? $pkg->from_date->format('Y-m-d') : '';
        $this->to_date = $pkg->to_date ? $pkg->to_date->format('Y-m-d') : '';
        $this->sort_order = (int) $pkg->sort_order;
        $this->features_text = is_array($pkg->features) ? implode("\n", $pkg->features) : '';

        $this->showFormModal = true;
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->amount = '0.00';
        $this->days = 30;
        $this->total_percentage = '0.00';
        $this->payment_gateway_percentage = '0.00';
        $this->commission_percentage = '0.00';
        $this->is_active = true;
        $this->from_date = '';
        $this->to_date = '';
        $this->sort_order = 0;
        $this->features_text = '';
    }

    public function savePackage()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'days' => 'required|integer|min:1',
            'payment_gateway_percentage' => 'required|numeric|min:0|max:100',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'total_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'sort_order' => 'integer|min:0',
        ]);

        $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $this->features_text))));

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'days' => (int) $this->days,
            'total_percentage' => (float) $this->total_percentage,
            'payment_gateway_percentage' => (float) $this->payment_gateway_percentage,
            'commission_percentage' => (float) $this->commission_percentage,
            'is_active' => $this->is_active,
            'from_date' => $this->from_date ?: null,
            'to_date' => $this->to_date ?: null,
            'sort_order' => (int) $this->sort_order,
            'features' => $featuresArray,
        ];

        if ($this->editingId) {
            $pkg = SubscriptionPackage::findOrFail($this->editingId);
            $pkg->update($data);
            session()->flash('status', 'Subscription Package updated successfully!');
        } else {
            SubscriptionPackage::create($data);
            session()->flash('status', 'New Subscription Package created successfully!');
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
            session()->flash('status', 'Package status updated successfully.');
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
                session()->flash('status', 'Subscription Package deleted successfully.');
            }
        }
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }
}; ?>

<div class="py-6 w-full">
    <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Top Header & Action Controls (Right Aligned Button) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full">
            <div>
                <h1 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30 dark:shadow-none shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    Subscription Packages
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Manage subscription packages, pricing amounts, validity days, platform commission, and payment gateway percentages.
                </p>
            </div>

            <!-- Create New Package Button (Right Aligned) -->
            <div class="sm:ms-auto shrink-0">
                <button wire:click="openCreateModal" type="button" 
                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs font-bold transition flex items-center gap-2 shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 cursor-pointer whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create New Package
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Filter & Search Bar -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 sm:p-5 shadow-xs border border-gray-200 dark:border-gray-700/80 flex flex-col md:flex-row items-center justify-between gap-4 w-full">
            <!-- Search input (Icon & Text Overlap Fixed with Absolute Left Container) -->
            <div class="relative w-full md:w-96">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search package name or details..." 
                    class="w-full pl-10 pr-4 py-2.5 text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <!-- Filter Controls -->
            <div class="flex items-center gap-3 w-full md:w-auto">
                <select wire:model.live="statusFilter" class="py-2.5 px-3 text-xs rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">All Packages</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>

                <select wire:model.live="sortBy" class="py-2.5 px-3 text-xs rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="sort_order">Display Order</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="days">Duration Days</option>
                    <option value="newest">Newest First</option>
                </select>
            </div>
        </div>

        @php
            $query = SubscriptionPackage::query();

            if (trim($this->search) !== '') {
                $s = trim($this->search);
                $query->where(function($q) use ($s) {
                    $q->where('name', 'LIKE', "%{$s}%")
                      ->orWhere('description', 'LIKE', "%{$s}%");
                });
            }

            if ($this->statusFilter === 'active') {
                $query->where('is_active', true);
            } elseif ($this->statusFilter === 'inactive') {
                $query->where('is_active', false);
            }

            if ($this->sortBy === 'price_asc') {
                $query->orderBy('amount', 'asc');
            } elseif ($this->sortBy === 'price_desc') {
                $query->orderBy('amount', 'desc');
            } elseif ($this->sortBy === 'days') {
                $query->orderBy('days', 'desc');
            } elseif ($this->sortBy === 'newest') {
                $query->orderBy('created_at', 'desc');
            } else {
                $query->orderBy('sort_order', 'asc')->orderBy('amount', 'asc');
            }

            $packages = $query->get();
        @endphp

        <!-- PACKAGES CARD LIST GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 w-full">
            @forelse ($packages as $pkg)
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm flex flex-col justify-between relative transition hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md group">
                    
                    <!-- Card Top Header -->
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <h3 class="text-lg font-black text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition leading-snug">
                                {{ $pkg->name }}
                            </h3>

                            <!-- Active / Inactive Status Switch -->
                            <button wire:click="toggleActive({{ $pkg->id }})" type="button" 
                                title="Click to toggle status"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase transition cursor-pointer shrink-0 {{ $pkg->is_active ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600' }}">
                                {{ $pkg->is_active ? '● Active' : '○ Inactive' }}
                            </button>
                        </div>

                        <!-- Price & Duration Box -->
                        <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 my-3 flex items-baseline justify-between">
                            <div>
                                <span class="text-2xl font-black text-gray-900 dark:text-white">₹{{ number_format($pkg->amount, 2) }}</span>
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 block sm:inline">/ {{ $pkg->days }} Days</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Duration</span>
                                <span class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400">
                                    {{ $pkg->days }} Days
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        @if ($pkg->description)
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-3 line-clamp-2">
                                {{ $pkg->description }}
                            </p>
                        @endif

                        <!-- Percentage Breakdown Chips -->
                        <div class="space-y-1.5 mb-3">
                            <span class="text-[10px] font-bold uppercase text-gray-400 tracking-wider">Percentages Breakdown</span>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="p-2.5 rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-900/60 text-center">
                                    <span class="text-[9px] font-extrabold text-indigo-600 dark:text-indigo-400 block uppercase">Total</span>
                                    <span class="text-xs font-black text-indigo-700 dark:text-indigo-300">{{ $pkg->total_percentage }}%</span>
                                </div>
                                <div class="p-2.5 rounded-2xl bg-emerald-50/80 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-900/60 text-center">
                                    <span class="text-[9px] font-extrabold text-emerald-600 dark:text-emerald-400 block uppercase">Gateway</span>
                                    <span class="text-xs font-black text-emerald-700 dark:text-emerald-300">{{ $pkg->payment_gateway_percentage }}%</span>
                                </div>
                                <div class="p-2.5 rounded-2xl bg-amber-50/80 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-900/60 text-center">
                                    <span class="text-[9px] font-extrabold text-amber-600 dark:text-amber-400 block uppercase">Comm.</span>
                                    <span class="text-xs font-black text-amber-700 dark:text-amber-300">{{ $pkg->commission_percentage }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Date Range Validity -->
                        @if ($pkg->from_date || $pkg->to_date)
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mb-3 py-2 border-t border-gray-100 dark:border-gray-700/60">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="truncate">Valid: <strong>{{ $pkg->from_date ? $pkg->from_date->format('d M Y') : 'Start' }}</strong> - <strong>{{ $pkg->to_date ? $pkg->to_date->format('d M Y') : 'Ongoing' }}</strong></span>
                            </div>
                        @endif

                        <!-- Feature Bullet Points -->
                        @if (is_array($pkg->features) && count($pkg->features) > 0)
                            <div class="space-y-1.5 mb-4">
                                <span class="text-[10px] font-bold uppercase text-gray-400 tracking-wider">Features</span>
                                <ul class="space-y-1">
                                    @foreach ($pkg->features as $feat)
                                        <li class="text-xs text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            <span class="truncate">{{ $feat }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <!-- Card Actions Footer -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center gap-2">
                        <button wire:click="openEditModal({{ $pkg->id }})" type="button" 
                            class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-700/80 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit
                        </button>
                        <button wire:click="confirmDelete({{ $pkg->id }})" type="button" 
                            class="px-3.5 py-2.5 bg-red-50 dark:bg-red-950/60 hover:bg-red-100 dark:hover:bg-red-900/80 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800/80 rounded-xl text-xs font-bold transition flex items-center justify-center cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-200 dark:border-gray-700 text-center text-gray-500 dark:text-gray-400 space-y-3">
                    <span class="text-4xl block">📦</span>
                    <p class="font-bold text-gray-800 dark:text-gray-200">No subscription packages found.</p>
                    <button wire:click="openCreateModal" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold cursor-pointer">
                        Create Your First Package
                    </button>
                </div>
            @endforelse
        </div>

    </div>

    <!-- CREATE / EDIT PACKAGE MODAL -->
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/70 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">SAAS Package Configuration</span>
                        <h2 class="text-xl font-black text-gray-900 dark:text-white mt-0.5">
                            {{ $editingId ? 'Edit Subscription Package' : 'Create New Subscription Package' }}
                        </h2>
                    </div>
                    <button wire:click="$set('showFormModal', false)" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700">✕</button>
                </div>

                <form wire:submit.prevent="savePackage" class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Package Name *</label>
                        <input type="text" wire:model="name" placeholder="e.g. Pro Monthly Plan" 
                            class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Amount, Days, Sort Order -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Amount (₹) *</label>
                            <input type="number" step="0.01" wire:model="amount" placeholder="2999.00" 
                                class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-bold">
                            @error('amount') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Validity Days *</label>
                            <input type="number" wire:model="days" placeholder="30" 
                                class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-bold">
                            @error('days') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Display Sort Order</label>
                            <input type="number" wire:model="sort_order" placeholder="0" 
                                class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <!-- Percentage Breakdown Box (Fixed Colors in Light & Dark Mode) -->
                    <div class="p-5 rounded-2xl bg-gray-100/70 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 space-y-4">
                        <span class="text-xs font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400 block">
                            Percentages Breakdown
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200 mb-1.5">Gateway Percentage (%) *</label>
                                <input type="number" step="0.01" wire:model.live="payment_gateway_percentage" placeholder="2.00" 
                                    class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">
                                @error('payment_gateway_percentage') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200 mb-1.5">Commission Percentage (%) *</label>
                                <input type="number" step="0.01" wire:model.live="commission_percentage" placeholder="3.00" 
                                    class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">
                                @error('commission_percentage') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200 mb-1.5">Total Percentage (%) *</label>
                                <input type="number" step="0.01" wire:model="total_percentage" placeholder="5.00" 
                                    class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-indigo-600 dark:text-indigo-400 font-black">
                                @error('total_percentage') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- From & To Date (with Light/White Calendar Icon in Dark Mode) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                            <input type="date" wire:model="from_date" 
                                class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                            <input type="date" wire:model="to_date" 
                                class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:[color-scheme:dark]">
                            @error('to_date') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea wire:model="description" rows="2" placeholder="Brief package description..." 
                            class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"></textarea>
                    </div>

                    <!-- Is Active Toggle -->
                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" id="is_active_check" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_active_check" class="text-xs font-bold text-gray-800 dark:text-gray-200 cursor-pointer">
                            Is Active
                        </label>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-bold">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-500/20 cursor-pointer">
                            {{ $editingId ? 'Save Changes' : 'Create Package' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- DELETE CONFIRMATION MODAL -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/70 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-2xl space-y-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 text-red-600 dark:text-red-400">
                    <div class="w-10 h-10 rounded-2xl bg-red-100 dark:bg-red-950/60 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-black text-lg text-gray-900 dark:text-white">Delete Package</h3>
                        <p class="text-xs text-gray-500">Are you sure you want to delete this subscription package?</p>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                    <button wire:click="$set('showDeleteModal', false)" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-bold">Cancel</button>
                    <button wire:click="deletePackage" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
