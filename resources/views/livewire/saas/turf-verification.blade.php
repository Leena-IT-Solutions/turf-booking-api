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
            @foreach(['All', 'Pending', 'Approved', 'Review', 'Rejected', 'Hold'] as $filter)
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

        <!-- Turfs List -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/50">
                    <thead class="bg-gray-50 dark:bg-gray-900/30">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Turf Info') }}</th>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Location & Address') }}</th>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Owner Details') }}</th>
                            <th scope="col" class="px-6 py-4 class-left text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Current Status') }}</th>
                            <th scope="col" class="px-6 py-4 text-right text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/40">
                        @forelse ($turfs as $turf)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/10 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 shrink-0 rounded-xl bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $turf->name }}</div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
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
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $turf->location->name }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-medium mt-1 truncate max-w-[200px]">{{ $turf->location->address }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $turf->location->user->name }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-medium mt-0.5">{{ $turf->location->user->email }}</div>
                                    <div class="text-[9px] text-indigo-500 dark:text-indigo-400 font-bold mt-0.5">{{ $turf->location->user->mobile }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-bold">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Approve Button -->
                                        @if($turf->status !== 'Approved')
                                            <button 
                                                type="button" 
                                                wire:click="updateStatus({{ $turf->id }}, 'Approved')" 
                                                title="{{ __('Approve') }}"
                                                class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/20 dark:hover:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-xl transition border border-emerald-100/50 dark:border-emerald-900/30 flex items-center gap-1.5 cursor-pointer"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span class="text-[9px] uppercase tracking-wider font-extrabold">{{ __('Approve') }}</span>
                                            </button>
                                        @endif

                                        <!-- Review Button -->
                                        @if($turf->status !== 'Review')
                                            <button 
                                                type="button" 
                                                wire:click="updateStatus({{ $turf->id }}, 'Review')" 
                                                title="{{ __('Needs Review') }}"
                                                class="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/20 dark:hover:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl transition border border-indigo-100/50 dark:border-indigo-900/30 flex items-center gap-1.5 cursor-pointer"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                <span class="text-[9px] uppercase tracking-wider font-extrabold">{{ __('Review') }}</span>
                                            </button>
                                        @endif

                                        <!-- Hold Button -->
                                        @if($turf->status !== 'Hold')
                                            <button 
                                                type="button" 
                                                wire:click="updateStatus({{ $turf->id }}, 'Hold')" 
                                                title="{{ __('Put on Hold') }}"
                                                class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 dark:bg-slate-900/40 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl transition border border-slate-200/50 dark:border-slate-700/30 flex items-center gap-1.5 cursor-pointer"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="text-[9px] uppercase tracking-wider font-extrabold">{{ __('Hold') }}</span>
                                            </button>
                                        @endif

                                        <!-- Reject Button -->
                                        @if($turf->status !== 'Rejected')
                                            <button 
                                                type="button" 
                                                wire:click="updateStatus({{ $turf->id }}, 'Rejected')" 
                                                title="{{ __('Reject') }}"
                                                class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-900/30 text-red-600 dark:text-red-450 rounded-xl transition border border-red-100/50 dark:border-red-900/30 flex items-center gap-1.5 cursor-pointer"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                <span class="text-[9px] uppercase tracking-wider font-extrabold">{{ __('Reject') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                    {{ __('No turfs found matching criteria.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if ($turfs->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700/40">
                    {{ $turfs->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
