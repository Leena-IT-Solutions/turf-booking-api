<?php

use App\Models\Turf;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'All';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updateStatus($turfId, $status)
    {
        $turf = Turf::findOrFail($turfId);
        $turf->update(['status' => $status]);
        session()->flash('status', __('Turf status updated to :status successfully.', ['status' => $status]));
    }

    public function with()
    {
        $query = Turf::with(['location.user']);

        if ($this->statusFilter !== 'All') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%')
                  ->orWhereHas('location', function ($l) {
                      $l->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('address', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function ($u) {
                            $u->where('name', 'like', '%' . $this->search . '%')
                              ->orWhere('email', 'like', '%' . $this->search . '%')
                              ->orWhere('mobile', 'like', '%' . $this->search . '%');
                        });
                  });
            });
        }

        // Get status counts for the tabs
        $counts = [
            'All' => Turf::count(),
            'Draft' => Turf::where('status', 'Draft')->count(),
            'Pending' => Turf::where('status', 'Pending')->count(),
            'Approved' => Turf::where('status', 'Approved')->count(),
            'Review' => Turf::where('status', 'Review')->count(),
            'Rejected' => Turf::where('status', 'Rejected')->count(),
            'Hold' => Turf::where('status', 'Hold')->count(),
        ];

        return [
            'turfs' => $query->latest()->paginate(10),
            'counts' => $counts,
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Turf Verification') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Review and manage the verification status of sports turfs submitted by administrators.') }}</p>
            </div>
        </div>

        <!-- Status Flash Message -->
        @if (session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 text-emerald-800 dark:text-emerald-400 text-sm font-medium shadow-sm transition">
                {{ session('status') }}
            </div>
        @endif

        <!-- Filter Tab Buttons -->
        <div class="flex flex-wrap gap-2 pb-1">
            @foreach(['All', 'Draft', 'Pending', 'Approved', 'Review', 'Rejected', 'Hold'] as $filter)
                <button 
                    type="button" 
                    wire:click="$set('statusFilter', '{{ $filter }}')" 
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl border transition-all cursor-pointer flex items-center gap-2 {{
                        $statusFilter === $filter 
                            ? 'bg-indigo-650 border-indigo-600 text-white shadow-md shadow-indigo-500/10' 
                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-705'
                    }}"
                >
                    {{ __($filter) }}
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-[9px] font-black rounded-full {{
                        $statusFilter === $filter
                            ? 'bg-white/20 text-white'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                    }}">
                        {{ $counts[$filter] }}
                    </span>
                </button>
            @endforeach
        </div>

        <!-- Search Bar -->
        <div class="relative bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
            <div class="absolute inset-y-0 start-4 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400 dark:text-gray-500 ms-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Search turfs by name, type, area, location, or owner name/email/mobile...') }}"
                class="w-full pl-12 pr-4 py-2.5 bg-gray-50 dark:bg-gray-900/40 text-xs font-semibold text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/80 transition duration-150"
            />
        </div>

        <!-- Turfs Card Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse ($turfs as $turf)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between relative group">
                    <div>
                        <!-- Turf Info Header -->
                        <div class="flex items-start justify-between gap-4 min-w-0 w-full">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">
                                        {{ $turf->name }}
                                    </h3>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider {{
                                            $turf->type === 'Synthetic' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : (
                                            $turf->type === 'Hard' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' :
                                            'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400')
                                        }}">
                                            {{ $turf->type }}
                                        </span>
                                        @if($turf->area)
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ $turf->area }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Current Status Badge -->
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider {{
                                $turf->status === 'Approved' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : (
                                $turf->status === 'Pending' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : (
                                $turf->status === 'Review' ? 'bg-indigo-100 dark:bg-indigo-950/20 text-indigo-700 dark:text-indigo-400' : (
                                $turf->status === 'Rejected' ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : (
                                $turf->status === 'Hold' ? 'bg-slate-100 dark:bg-slate-900/30 text-slate-700 dark:text-slate-400' :
                                'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'))))
                            }}">
                                {{ __($turf->status ?: 'Draft') }}
                            </span>
                        </div>

                        <!-- Location Section -->
                        <div class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-700/40 space-y-1">
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider block">{{ __('Location & Address') }}</span>
                            <div class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $turf->location->name }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400 leading-relaxed font-semibold">{{ $turf->location->address }}</div>
                        </div>

                        <!-- Owner Section -->
                        <div class="mt-3 pt-3 border-t border-gray-50 dark:border-gray-700/40 space-y-1">
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider block">{{ __('Owner Details') }}</span>
                            <div class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $turf->location->user->name }}</div>
                            <div class="text-[10px] text-gray-550 dark:text-gray-400 font-semibold flex flex-col gap-0.5">
                                <span>{{ $turf->location->user->email }}</span>
                                <span class="text-indigo-500 dark:text-indigo-400 font-bold">{{ $turf->location->user->mobile }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-50 dark:border-gray-700/40">
                        <div class="grid grid-cols-2 gap-2">
                            <!-- Approve Button -->
                            @if($turf->status !== 'Approved')
                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $turf->id }}, 'Approved')" 
                                    class="px-2 py-2 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/20 dark:hover:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-xl transition border border-emerald-100/50 dark:border-emerald-900/30 flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __('Approve') }}
                                </button>
                            @endif

                            <!-- Review Button -->
                            @if($turf->status !== 'Review')
                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $turf->id }}, 'Review')" 
                                    class="px-2 py-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/20 dark:hover:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl transition border border-indigo-100/50 dark:border-indigo-900/30 flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    {{ __('Review') }}
                                </button>
                            @endif

                            <!-- Hold Button -->
                            @if($turf->status !== 'Hold')
                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $turf->id }}, 'Hold')" 
                                    class="px-2 py-2 bg-slate-50 hover:bg-slate-100 dark:bg-slate-900/40 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl transition border border-slate-200/50 dark:border-slate-700/30 flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Hold') }}
                                </button>
                            @endif

                            <!-- Reject Button -->
                            @if($turf->status !== 'Rejected')
                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $turf->id }}, 'Rejected')" 
                                    class="px-2 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-900/30 text-red-600 dark:text-red-450 rounded-xl transition border border-red-100/50 dark:border-red-900/30 flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    {{ __('Reject') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 xl:col-span-3 bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-100 dark:border-gray-700/50 text-center">
                    <p class="text-xs font-semibold text-gray-450 dark:text-gray-500 uppercase tracking-wider">
                        {{ __('No turfs found matching criteria.') }}
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Pagination Links -->
        @if ($turfs->hasPages())
            <div class="bg-white dark:bg-gray-800 px-6 py-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                {{ $turfs->links() }}
            </div>
        @endif

    </div>
</div>
