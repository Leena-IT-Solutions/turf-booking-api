<?php

use App\Models\PaymentGatewayCharge;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Search & Filters
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
    public string $code = '';
    public string $charge_percentage = '2.00';
    public string $tax_percentage = '18.00';
    public string $total_percentage = '2.3600';
    public string $flat_fee = '0.00';
    public string $description = '';
    public bool $is_active = true;
    public int $sort_order = 0;

    public function updatedChargePercentage()
    {
        $this->recalculateTotal();
    }

    public function updatedTaxPercentage()
    {
        $this->recalculateTotal();
    }

    public function recalculateTotal(): void
    {
        $charge = is_numeric($this->charge_percentage) ? (float) $this->charge_percentage : 0.0;
        $tax = is_numeric($this->tax_percentage) ? (float) $this->tax_percentage : 0.0;
        $this->total_percentage = number_format(PaymentGatewayCharge::calculateTotalPercentage($charge, $tax), 4, '.', '');
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->recalculateTotal();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $charge = PaymentGatewayCharge::find($id);
        if (!$charge) return;

        $this->editingId = $charge->id;
        $this->name = $charge->name;
        $this->code = $charge->code ?? '';
        $this->charge_percentage = (string) $charge->charge_percentage;
        $this->tax_percentage = (string) $charge->tax_percentage;
        $this->total_percentage = number_format($charge->total_percentage, 4, '.', '');
        $this->flat_fee = (string) $charge->flat_fee;
        $this->description = $charge->description ?? '';
        $this->is_active = (bool) $charge->is_active;
        $this->sort_order = (int) $charge->sort_order;

        $this->showFormModal = true;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->code = '';
        $this->charge_percentage = '2.00';
        $this->tax_percentage = '18.00';
        $this->total_percentage = '2.3600';
        $this->flat_fee = '0.00';
        $this->description = '';
        $this->is_active = true;
        $this->sort_order = PaymentGatewayCharge::max('sort_order') + 1;
    }

    public function saveCharge(): void
    {
        $rules = [
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50|unique:payment_gateway_charges,code,' . ($this->editingId ?? 'NULL') . ',id',
            'charge_percentage' => 'required|numeric|min:0|max:100',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'total_percentage' => 'required|numeric|min:0|max:100',
            'flat_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];

        $this->validate($rules);

        $data = [
            'name' => trim($this->name),
            'code' => $this->code ? strtolower(trim($this->code)) : null,
            'charge_percentage' => (float) $this->charge_percentage,
            'tax_percentage' => (float) $this->tax_percentage,
            'total_percentage' => (float) $this->total_percentage,
            'flat_fee' => $this->flat_fee ? (float) $this->flat_fee : 0.00,
            'description' => $this->description ? trim($this->description) : null,
            'is_active' => $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];

        if ($this->editingId) {
            $charge = PaymentGatewayCharge::findOrFail($this->editingId);
            $charge->update($data);
            session()->flash('status', __('Payment method charge updated successfully.'));
        } else {
            PaymentGatewayCharge::create($data);
            session()->flash('status', __('New payment method charge added successfully.'));
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $charge = PaymentGatewayCharge::find($id);
        if ($charge) {
            $charge->is_active = !$charge->is_active;
            $charge->save();
            session()->flash('status', __(':name status updated.', ['name' => $charge->name]));
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteCharge(): void
    {
        if ($this->deletingId) {
            $charge = PaymentGatewayCharge::find($this->deletingId);
            if ($charge) {
                $charge->delete();
                session()->flash('status', __('Payment method charge removed successfully.'));
            }
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function with(): array
    {
        $query = PaymentGatewayCharge::query();

        if ($this->search) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        switch ($this->sortBy) {
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'charge_percentage':
                $query->orderBy('charge_percentage', 'asc');
                break;
            case 'total_percentage':
                $query->orderBy('total_percentage', 'asc');
                break;
            case 'sort_order':
            default:
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
                break;
        }

        $charges = $query->get();

        $totalCount = PaymentGatewayCharge::count();
        $tier2Count = PaymentGatewayCharge::where('charge_percentage', '<=', 2.00)->count();
        $tier3Count = PaymentGatewayCharge::where('charge_percentage', '>', 2.00)->count();

        return [
            'charges' => $charges,
            'totalCount' => $totalCount,
            'tier2Count' => $tier2Count,
            'tier3Count' => $tier3Count,
        ];
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Top Header Card -->
        <div class="bg-white p-6 sm:p-8 shadow-sm rounded-3xl border border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Payment Gateway Charges') }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Configure transaction fees, taxes, and effective deduction rates displayed to turf owners.') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="openCreateModal" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition duration-150 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ __('Add Payment Method') }}</span>
                </button>
            </div>
        </div>

        <!-- Metric / Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Card 1: Total Rails -->
            <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">{{ __('Configured Methods') }}</span>
                    <span class="text-2xl font-black text-gray-900 mt-1 block">{{ $totalCount }}</span>
                    <span class="text-[10px] text-gray-400 mt-0.5 block">{{ __('Payment rails supported') }}</span>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                </div>
            </div>

            <!-- Card 2: 2% Tier -->
            <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">{{ __('2% Base Tier') }}</span>
                    <span class="text-2xl font-black text-emerald-600 mt-1 block">{{ $tier2Count }}</span>
                    <span class="text-[10px] text-emerald-700 font-semibold mt-0.5 block">{{ __('UPI, RuPay, NetBanking (2.36% total)') }}</span>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
            </div>

            <!-- Card 3: 3% Tier -->
            <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">{{ __('3% Premium Tier') }}</span>
                    <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $tier3Count }}</span>
                    <span class="text-[10px] text-amber-700 font-semibold mt-0.5 block">{{ __('Credit Cards, Amex, EMI (3.54% total)') }}</span>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            <!-- Card 4: Statutory GST Rate -->
            <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">{{ __('GST on PG Charges') }}</span>
                    <span class="text-2xl font-black text-purple-600 mt-1 block">18.00%</span>
                    <span class="text-[10px] text-purple-700 font-semibold mt-0.5 block">{{ __('Applied on gateway fee') }}</span>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-purple-50 border border-purple-100 text-purple-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                </div>
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

        <!-- Filter & Search Bar Card -->
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-gray-100 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
            <div class="relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="{{ __('Search payment method or code...') }}" 
                    class="w-full pl-10 pr-4 py-2 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-medium text-gray-900 transition" />
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <!-- Status Filter -->
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{{ __('Status:') }}</span>
                    <select wire:model.live="statusFilter" class="py-2 pl-3 pr-8 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-700 transition">
                        <option value="all">{{ __('All Status') }}</option>
                        <option value="active">{{ __('Active Only') }}</option>
                        <option value="inactive">{{ __('Inactive Only') }}</option>
                    </select>
                </div>

                <!-- Sort Filter -->
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{{ __('Sort:') }}</span>
                    <select wire:model.live="sortBy" class="py-2 pl-3 pr-8 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-700 transition">
                        <option value="sort_order">{{ __('Sort Order') }}</option>
                        <option value="name">{{ __('Method Name') }}</option>
                        <option value="charge_percentage">{{ __('Base Charge %') }}</option>
                        <option value="total_percentage">{{ __('Total Deduction %') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Charges Table Card -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider w-16 text-center">{{ __('Order') }}</th>
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider">{{ __('Payment Method') }}</th>
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider text-right">{{ __('Gateway Fee') }}</th>
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider text-right">{{ __('Tax (GST)') }}</th>
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider text-right">{{ __('Total Deduction') }}</th>
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider text-center">{{ __('Status') }}</th>
                            <th class="py-4 px-6 text-[10px] font-black uppercase text-gray-400 tracking-wider text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($charges as $charge)
                            <tr class="hover:bg-gray-50/50 transition">
                                <!-- Order -->
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-gray-100 text-[11px] font-bold text-gray-500">
                                        {{ $charge->sort_order }}
                                    </span>
                                </td>

                                <!-- Payment Method -->
                                <td class="py-4 px-6">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-900">{{ $charge->name }}</span>
                                            @if ($charge->code)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold font-mono uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                    {{ $charge->code }}
                                                </span>
                                            @endif
                                        </div>
                                        @if ($charge->description)
                                            <p class="text-[11px] text-gray-400 font-medium">{{ $charge->description }}</p>
                                        @endif
                                    </div>
                                </td>

                                <!-- Gateway Fee -->
                                <td class="py-4 px-6 text-right">
                                    <div class="inline-flex flex-col items-end">
                                        <span class="text-xs font-bold text-gray-900 font-mono">
                                            {{ number_format($charge->charge_percentage, 2) }}%
                                        </span>
                                        @if ($charge->flat_fee > 0)
                                            <span class="text-[10px] text-gray-400 font-medium">+ ₹{{ number_format($charge->flat_fee, 2) }}</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Tax % -->
                                <td class="py-4 px-6 text-right">
                                    <span class="text-xs font-bold text-gray-600 font-mono">
                                        {{ number_format($charge->tax_percentage, 2) }}%
                                    </span>
                                </td>

                                <!-- Total Percentage -->
                                <td class="py-4 px-6 text-right">
                                    <div class="inline-flex flex-col items-end">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-extrabold font-mono tracking-tight {{ $charge->charge_percentage > 2.00 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                            {{ number_format($charge->total_percentage, 2) }}%
                                        </span>
                                        <span class="text-[9px] text-gray-400 mt-0.5 font-medium">
                                            (₹{{ number_format(1000 * ($charge->total_percentage / 100), 2) }} / ₹1k)
                                        </span>
                                    </div>
                                </td>

                                <!-- Status Toggle -->
                                <td class="py-4 px-6 text-center">
                                    <button wire:click="toggleActive({{ $charge->id }})" type="button" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase transition {{ $charge->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-500 border border-gray-200 hover:bg-gray-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $charge->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                        <span>{{ $charge->is_active ? __('Active') : __('Inactive') }}</span>
                                    </button>
                                </td>

                                <!-- Actions -->
                                <td class="py-4 px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button wire:click="openEditModal({{ $charge->id }})" type="button" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition cursor-pointer" title="{{ __('Edit') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $charge->id }})" type="button" class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer" title="{{ __('Delete') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-xs text-gray-400 font-medium">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                        <span>{{ __('No payment gateway charges configured yet.') }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        @if ($showFormModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div wire:click="$set('showFormModal', false)" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity cursor-pointer"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-gray-100">
                        <form wire:submit="saveCharge">
                            <!-- Modal Header -->
                            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-gray-900">{{ $editingId ? __('Edit Payment Method Charge') : __('Add Payment Method Charge') }}</h3>
                                        <p class="text-xs text-gray-500">{{ __('Define gateway rate and tax percentage.') }}</p>
                                    </div>
                                </div>
                                <button wire:click="$set('showFormModal', false)" type="button" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="p-6 space-y-5">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Name -->
                                    <div class="sm:col-span-2">
                                        <label for="chargeName" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Payment Method Name *') }}</label>
                                        <input wire:model.live.debounce.250ms="name" id="chargeName" type="text" 
                                            class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-900 transition" 
                                            placeholder="e.g. Credit Cards (Domestic)" />
                                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                                    </div>

                                    <!-- Code -->
                                    <div>
                                        <label for="chargeCode" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Identifier Code (Slug)') }}</label>
                                        <input wire:model.live.debounce.250ms="code" id="chargeCode" type="text" 
                                            class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-mono font-bold text-gray-900 uppercase transition" 
                                            placeholder="e.g. credit_card_domestic" />
                                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                                    </div>

                                    <!-- Sort Order -->
                                    <div>
                                        <label for="sortOrder" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Display Order') }}</label>
                                        <input wire:model.live.debounce.250ms="sort_order" id="sortOrder" type="number" min="0" 
                                            class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 text-center transition" />
                                        <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                                    </div>

                                    <!-- Base Charge % -->
                                    <div>
                                        <label for="baseChargePct" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Base Gateway Charge (%) *') }}</label>
                                        <div class="relative">
                                            <input wire:model.live.debounce.250ms="charge_percentage" id="baseChargePct" type="number" step="0.01" min="0" max="100" 
                                                class="w-full pr-8 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 text-right transition" 
                                                placeholder="2.00" />
                                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                                        </div>
                                        <x-input-error :messages="$errors->get('charge_percentage')" class="mt-1" />
                                    </div>

                                    <!-- Tax % -->
                                    <div>
                                        <label for="taxPct" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Tax Rate (GST %) *') }}</label>
                                        <div class="relative">
                                            <input wire:model.live.debounce.250ms="tax_percentage" id="taxPct" type="number" step="0.01" min="0" max="100" 
                                                class="w-full pr-8 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 text-right transition" 
                                                placeholder="18.00" />
                                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                                        </div>
                                        <x-input-error :messages="$errors->get('tax_percentage')" class="mt-1" />
                                    </div>

                                    <!-- Total Effective % -->
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="totalPct" class="text-xs font-bold text-indigo-700 block">{{ __('Total Effective Rate (%) *') }}</label>
                                            <button wire:click="recalculateTotal" type="button" class="text-[10px] text-indigo-600 font-bold hover:underline cursor-pointer">
                                                {{ __('Recalculate') }}
                                            </button>
                                        </div>
                                        <div class="relative">
                                            <input wire:model.live.debounce.250ms="total_percentage" id="totalPct" type="number" step="0.0001" min="0" max="100" 
                                                class="w-full pr-8 pl-4 py-2.5 bg-indigo-50/40 hover:bg-white focus:bg-white rounded-2xl border border-indigo-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-extrabold font-mono text-indigo-900 text-right transition" 
                                                placeholder="2.36" />
                                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-indigo-400">%</span>
                                        </div>
                                        <x-input-error :messages="$errors->get('total_percentage')" class="mt-1" />
                                    </div>

                                    <!-- Optional Flat Fee -->
                                    <div>
                                        <label for="flatFee" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Flat Fee (Optional ₹)') }}</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">₹</span>
                                            <input wire:model.live.debounce.250ms="flat_fee" id="flatFee" type="number" step="0.01" min="0" 
                                                class="w-full pl-8 pr-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 text-right transition" 
                                                placeholder="0.00" />
                                        </div>
                                        <x-input-error :messages="$errors->get('flat_fee')" class="mt-1" />
                                    </div>

                                    <!-- Description -->
                                    <div class="sm:col-span-2">
                                        <label for="chargeDesc" class="text-xs font-bold text-gray-900 block mb-1">{{ __('Description / Subtitle') }}</label>
                                        <input wire:model.live.debounce.250ms="description" id="chargeDesc" type="text" 
                                            class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-medium text-gray-900 transition" 
                                            placeholder="e.g. All domestic Visa and Mastercard credit cards" />
                                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                                    </div>
                                </div>

                                <!-- Live Calculation Breakdown Preview -->
                                @php
                                    $calcBase = is_numeric($charge_percentage) ? (float) $charge_percentage : 0;
                                    $calcTax = is_numeric($tax_percentage) ? (float) $tax_percentage : 0;
                                    $calcTotal = is_numeric($total_percentage) ? (float) $total_percentage : 0;
                                    $sampleAmount = 1000;
                                    $feeAmount = ($sampleAmount * $calcBase) / 100;
                                    $taxAmount = ($feeAmount * $calcTax) / 100;
                                    $totalDeduction = $feeAmount + $taxAmount;
                                @endphp
                                <div class="bg-indigo-50/60 border border-indigo-100 rounded-2xl p-4 space-y-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 block flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ __('Live Calculation Example (On ₹1,000 Booking)') }}
                                    </span>
                                    <div class="grid grid-cols-3 gap-2 text-center pt-1">
                                        <div class="bg-white p-2 rounded-xl border border-indigo-100/50 shadow-2xs">
                                            <span class="text-[10px] text-gray-400 font-bold block">{{ __('Base PG Fee') }}</span>
                                            <span class="text-xs font-bold text-gray-900 font-mono">₹{{ number_format($feeAmount, 2) }}</span>
                                            <span class="text-[9px] text-gray-400 block">({{ number_format($calcBase, 2) }}%)</span>
                                        </div>
                                        <div class="bg-white p-2 rounded-xl border border-indigo-100/50 shadow-2xs">
                                            <span class="text-[10px] text-gray-400 font-bold block">{{ __('GST on Fee') }}</span>
                                            <span class="text-xs font-bold text-gray-900 font-mono">₹{{ number_format($taxAmount, 2) }}</span>
                                            <span class="text-[9px] text-gray-400 block">({{ number_format($calcTax, 2) }}%)</span>
                                        </div>
                                        <div class="bg-indigo-600 p-2 rounded-xl text-white shadow-xs">
                                            <span class="text-[10px] text-indigo-200 font-bold block">{{ __('Total Deducted') }}</span>
                                            <span class="text-xs font-bold font-mono">₹{{ number_format($totalDeduction, 2) }}</span>
                                            <span class="text-[9px] text-indigo-200 block">({{ number_format($calcTotal, 2) }}%)</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Active toggle -->
                                <div class="flex items-center justify-between pt-2">
                                    <div>
                                        <label for="isActiveToggle" class="text-xs font-bold text-gray-900 block cursor-pointer">{{ __('Active Status') }}</label>
                                        <p class="text-[11px] text-gray-400">{{ __('Visible to turf owners in commission & payout fee estimates.') }}</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input wire:model.live="is_active" id="isActiveToggle" type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                                    </label>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex items-center justify-end gap-3 rounded-b-3xl">
                                <button wire:click="$set('showFormModal', false)" type="button" class="px-5 py-2.5 bg-white hover:bg-gray-100 text-gray-700 rounded-xl font-bold text-xs uppercase tracking-wider border border-gray-200 transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition cursor-pointer disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="saveCharge" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg wire:loading wire:target="saveCharge" class="animate-spin w-4 h-4 text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>{{ $editingId ? __('Update Charge') : __('Save Charge') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Delete Confirmation Modal -->
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
                            <h3 class="text-base font-bold text-gray-900">{{ __('Remove Payment Method Charge?') }}</h3>
                            <p class="text-xs text-gray-500 leading-relaxed">{{ __('Are you sure you want to remove this payment method charge? Turf owners will no longer see this fee breakdown.') }}</p>
                        </div>
                        <div class="flex items-center justify-center gap-3 pt-2">
                            <button wire:click="$set('showDeleteModal', false)" type="button" class="w-full px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold text-xs uppercase tracking-wider transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button wire:click="deleteCharge" type="button" class="w-full px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition cursor-pointer">
                                {{ __('Yes, Remove') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
