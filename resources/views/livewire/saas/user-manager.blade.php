<?php

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Search and filters
    public $search = '';
    public $roleFilter = '';
    public $sortBy = 'newest'; // 'newest', 'oldest', 'name_asc', 'name_desc'

    // User Acquisition Chart state
    public $chartPeriod = 'month'; // 'day', 'week', 'month', 'year'
    public $chartCursor = '';

    // Form inputs
    public $name = '';
    public $email = '';
    public $mobile = '';
    public $password = '';
    public $selectedRoles = [];

    // State
    public $editingId = null;
    public $showModal = false;

    // Delete confirmation state
    public $deletingId = null;
    public $showDeleteConfirm = false;

    public function mount()
    {
        $this->chartCursor = Carbon::now('Asia/Kolkata')->toDateString();
    }

    public function setChartPeriod(string $period): void
    {
        $this->chartPeriod = $period;
    }

    public function previousPeriod(): void
    {
        $cursor = Carbon::parse($this->chartCursor ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
        if ($this->chartPeriod === 'day') {
            $cursor->subDay();
        } elseif ($this->chartPeriod === 'week') {
            $cursor->subWeek();
        } elseif ($this->chartPeriod === 'month') {
            $cursor->subMonth();
        } elseif ($this->chartPeriod === 'year') {
            $cursor->subYear();
        }
        $this->chartCursor = $cursor->toDateString();
    }

    public function nextPeriod(): void
    {
        $cursor = Carbon::parse($this->chartCursor ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
        if ($this->chartPeriod === 'day') {
            $cursor->addDay();
        } elseif ($this->chartPeriod === 'week') {
            $cursor->addWeek();
        } elseif ($this->chartPeriod === 'month') {
            $cursor->addMonth();
        } elseif ($this->chartPeriod === 'year') {
            $cursor->addYear();
        }
        $this->chartCursor = $cursor->toDateString();
    }

    public function goToCurrentPeriod(): void
    {
        $this->chartCursor = Carbon::now('Asia/Kolkata')->toDateString();
    }

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->showDeleteConfirm = false;
    }

    public function performDelete()
    {
        if ($this->deletingId) {
            $this->deleteUser($this->deletingId);
        }
        $this->cancelDelete();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->selectedRoles = ['customer'];
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset(['name', 'email', 'mobile', 'password', 'selectedRoles', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = false;
    }

    public function editUser($id)
    {
        $this->resetForm();
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = $user->mobile;
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function updated($propertyName)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->editingId),
            ],
            'mobile' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($this->editingId),
            ],
            'password' => $this->editingId ? 'nullable|string|min:8' : 'required|string|min:8',
            'selectedRoles' => 'required|array|min:1',
        ];

        $this->validateOnly($propertyName, $rules);
    }

    public function saveUser()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->editingId),
            ],
            'mobile' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($this->editingId),
            ],
            'password' => $this->editingId ? 'nullable|string|min:8' : 'required|string|min:8',
            'selectedRoles' => 'required|array|min:1',
        ];

        $validated = $this->validate($rules);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $hadTurfAdmin = $user->hasRole('turf-admin');

            $user->update([
                'name' => $this->name,
                'email' => $this->email,
                'mobile' => $this->mobile,
            ]);

            if ($this->password) {
                $user->update([
                    'password' => Hash::make($this->password),
                ]);
            }

            // Sync roles - force merge 'customer' so it cannot be removed
            $rolesToSync = array_unique(array_merge($this->selectedRoles, ['customer']));
            $user->roles()->sync(
                Role::whereIn('name', $rolesToSync)->pluck('id')->toArray()
            );

            if (!$hadTurfAdmin && in_array('turf-admin', $rolesToSync)) {
                $user->startTurfOwnerTrial();
            }

            session()->flash('status', 'User details updated successfully.');
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'mobile' => $this->mobile,
                'password' => Hash::make($this->password),
            ]);

            // Sync roles - force merge 'customer' so it cannot be removed
            $rolesToSync = array_unique(array_merge($this->selectedRoles, ['customer']));
            $user->roles()->sync(
                Role::whereIn('name', $rolesToSync)->pluck('id')->toArray()
            );

            if (in_array('turf-admin', $rolesToSync)) {
                $user->startTurfOwnerTrial();
            }

            session()->flash('status', 'User created successfully.');
        }

        $this->resetForm();
    }

    public function extendTrial($userId, ?int $days = null): void
    {
        $user = User::findOrFail($userId);
        $saas = \App\Models\SaasSetting::first();
        $daysToAdd = $days ?: (int) ($saas?->free_trial_days ?? 30);
        if ($daysToAdd <= 0) {
            $daysToAdd = 30;
        }

        $base = ($user->trial_ends_at && $user->trial_ends_at->isFuture())
            ? $user->trial_ends_at
            : now();

        if ($user->trial_started_at === null) {
            $user->trial_started_at = now();
        }

        $user->trial_ends_at = $base->copy()->addDays($daysToAdd);
        $user->save();

        session()->flash('status', "Free trial extended by {$daysToAdd} days for {$user->name}.");
    }

    public function deleteUser($id)
    {
        if ($id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user = User::findOrFail($id);
        $user->roles()->detach();
        $user->delete();

        session()->flash('status', 'User deleted successfully.');
    }

    protected function getAcquisitionChartData(): array
    {
        $cursor = Carbon::parse($this->chartCursor ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
        $now = Carbon::now('Asia/Kolkata');

        $data = [];
        $periodTitle = '';
        $periodSub = '';

        if ($this->chartPeriod === 'day') {
            $start = $cursor->copy()->startOfDay();
            $end = $cursor->copy()->endOfDay();
            $periodTitle = $cursor->isToday() ? 'Today, ' . $cursor->format('d M Y') : $cursor->format('l, d M Y');
            $periodSub = '24-hour acquisition timeline';

            // 12 2-hour blocks
            $blocks = [
                ['from' => '00:00:00', 'to' => '01:59:59', 'label' => '12 AM'],
                ['from' => '02:00:00', 'to' => '03:59:59', 'label' => '2 AM'],
                ['from' => '04:00:00', 'to' => '05:59:59', 'label' => '4 AM'],
                ['from' => '06:00:00', 'to' => '07:59:59', 'label' => '6 AM'],
                ['from' => '08:00:00', 'to' => '09:59:59', 'label' => '8 AM'],
                ['from' => '10:00:00', 'to' => '11:59:59', 'label' => '10 AM'],
                ['from' => '12:00:00', 'to' => '13:59:59', 'label' => '12 PM'],
                ['from' => '14:00:00', 'to' => '15:59:59', 'label' => '2 PM'],
                ['from' => '16:00:00', 'to' => '17:59:59', 'label' => '4 PM'],
                ['from' => '18:00:00', 'to' => '19:59:59', 'label' => '6 PM'],
                ['from' => '20:00:00', 'to' => '21:59:59', 'label' => '8 PM'],
                ['from' => '22:00:00', 'to' => '23:59:59', 'label' => '10 PM'],
            ];

            $users = User::whereBetween('created_at', [$start, $end])->get(['id', 'created_at']);

            foreach ($blocks as $b) {
                $count = $users->filter(function ($u) use ($b) {
                    $time = $u->created_at->setTimezone('Asia/Kolkata')->format('H:i:s');
                    return $time >= $b['from'] && $time <= $b['to'];
                })->count();

                $data[] = [
                    'label' => $b['label'],
                    'full_label' => $b['label'] . ' - ' . $cursor->format('d M'),
                    'count' => $count,
                    'is_current' => ($cursor->isToday() && $now->format('H:i:s') >= $b['from'] && $now->format('H:i:s') <= $b['to']),
                ];
            }
        } elseif ($this->chartPeriod === 'week') {
            $start = $cursor->copy()->startOfWeek();
            $end = $cursor->copy()->endOfWeek();
            $periodTitle = 'Week ' . $cursor->format('W') . ' (' . $start->format('d M') . ' – ' . $end->format('d M Y') . ')';
            $periodSub = 'Daily signups for the selected week';

            $users = User::whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])->get(['id', 'created_at']);

            for ($i = 0; $i < 7; $i++) {
                $day = $start->copy()->addDays($i);
                $dayStr = $day->toDateString();
                $count = $users->filter(function ($u) use ($dayStr) {
                    return $u->created_at->setTimezone('Asia/Kolkata')->toDateString() === $dayStr;
                })->count();

                $data[] = [
                    'label' => $day->format('D'),
                    'full_label' => $day->format('l, d M Y'),
                    'count' => $count,
                    'is_current' => $day->isToday(),
                ];
            }
        } elseif ($this->chartPeriod === 'month') {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();
            $periodTitle = $cursor->format('F Y');
            $periodSub = 'Daily breakdown for ' . $cursor->format('F Y');

            $users = User::whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])->get(['id', 'created_at']);
            $daysInMonth = $cursor->daysInMonth;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $day = $cursor->copy()->day($d);
                $dayStr = $day->toDateString();
                $count = $users->filter(function ($u) use ($dayStr) {
                    return $u->created_at->setTimezone('Asia/Kolkata')->toDateString() === $dayStr;
                })->count();

                $data[] = [
                    'label' => (string) $d,
                    'full_label' => $day->format('d M Y (D)'),
                    'count' => $count,
                    'is_current' => $day->isToday(),
                ];
            }
        } else { // 'year'
            $start = $cursor->copy()->startOfYear();
            $end = $cursor->copy()->endOfYear();
            $periodTitle = 'Year ' . $cursor->format('Y');
            $periodSub = 'Monthly acquisition distribution for ' . $cursor->format('Y');

            $users = User::whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])->get(['id', 'created_at']);

            for ($m = 1; $m <= 12; $m++) {
                $month = $cursor->copy()->month($m);
                $monthStr = $month->format('Y-m');
                $count = $users->filter(function ($u) use ($monthStr) {
                    return $u->created_at->setTimezone('Asia/Kolkata')->format('Y-m') === $monthStr;
                })->count();

                $data[] = [
                    'label' => $month->format('M'),
                    'full_label' => $month->format('F Y'),
                    'count' => $count,
                    'is_current' => ($cursor->isCurrentYear() && $month->month === $now->month),
                ];
            }
        }

        $totalPeriodCount = array_sum(array_column($data, 'count'));
        $maxCount = !empty($data) ? max(array_column($data, 'count')) : 0;

        return [
            'periodTitle' => $periodTitle,
            'periodSub' => $periodSub,
            'points' => $data,
            'totalPeriodCount' => $totalPeriodCount,
            'maxCount' => $maxCount,
        ];
    }

    public function with()
    {
        // 1. Total counts
        $totalUsersCount = User::count();
        $saasAdminsCount = User::whereHas('roles', fn($q) => $q->where('name', 'saas-admin'))->count();
        $turfAdminsCount = User::whereHas('roles', fn($q) => $q->where('name', 'turf-admin'))->count();
        $managersCount = User::whereHas('roles', fn($q) => $q->where('name', 'manager'))->count();
        $customersCount = User::whereHas('roles', fn($q) => $q->where('name', 'customer'))->count();

        // 2. Acquisition Chart data
        $chartData = $this->getAcquisitionChartData();

        // 3. User Listing query
        $query = User::query()->with('roles');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('mobile', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        match ($this->sortBy) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        return [
            'users' => $query->paginate(10),
            'availableRoles' => Role::all(),
            'totalUsersCount' => $totalUsersCount,
            'saasAdminsCount' => $saasAdminsCount,
            'turfAdminsCount' => $turfAdminsCount,
            'managersCount' => $managersCount,
            'customersCount' => $customersCount,
            'chartData' => $chartData,
        ];
    }
}; ?>

<div class="space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-xs rounded-3xl border border-gray-100">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-900">{{ __('User Account Management') }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ $totalUsersCount }} {{ $totalUsersCount === 1 ? __('User') : __('Total Users') }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1.5">{{ __('Administer global platform accounts, view user acquisition trends, and manage role permissions.') }}</p>
            </div>
            <div>
                <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    {{ __('Add New User') }}
                </button>
            </div>
        </div>

        @if (session()->has('status'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-rose-50 border border-rose-100 text-rose-800 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Total Count Summary Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
            <!-- Total Accounts -->
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Total Accounts') }}</span>
                    <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">👥</span>
                </div>
                <div class="mt-2 text-2xl font-black text-gray-900 font-mono">{{ $totalUsersCount }}</div>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('All registered profiles') }}</p>
            </div>

            <!-- Customers -->
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Customers') }}</span>
                    <span class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">⚽</span>
                </div>
                <div class="mt-2 text-2xl font-black text-gray-900 font-mono">{{ $customersCount }}</div>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Booking users') }}</p>
            </div>

            <!-- Turf Admins -->
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Turf Admins') }}</span>
                    <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">🏟️</span>
                </div>
                <div class="mt-2 text-2xl font-black text-gray-900 font-mono">{{ $turfAdminsCount }}</div>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Venue owners') }}</p>
            </div>

            <!-- Managers -->
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Managers') }}</span>
                    <span class="w-6 h-6 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">👔</span>
                </div>
                <div class="mt-2 text-2xl font-black text-gray-900 font-mono">{{ $managersCount }}</div>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Operations staff') }}</p>
            </div>

            <!-- SaaS Admins -->
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('SaaS Admins') }}</span>
                    <span class="w-6 h-6 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-xs">🛡️</span>
                </div>
                <div class="mt-2 text-2xl font-black text-gray-900 font-mono">{{ $saasAdminsCount }}</div>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Platform superusers') }}</p>
            </div>
        </div>

        <!-- User Acquisition Chart Card (Day, Week, Month, Year) with Prev / Next -->
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-5">
            <!-- Chart Header & Controls -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-bold text-gray-900 tracking-tight">{{ __('User Acquisition') }}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/50">
                                {{ ucfirst($chartPeriod) }} Wise
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span class="font-semibold text-gray-900">{{ $chartData['periodTitle'] }}</span>
                            <span class="text-gray-300 mx-1">•</span>
                            <span>{{ $chartData['periodSub'] }}</span>
                        </p>
                    </div>
                </div>

                <!-- Controls: Period Selector + Previous/Next Navigation -->
                <div class="flex flex-wrap items-center gap-2 self-start lg:self-auto">
                    <!-- Period Toggle Pills -->
                    <div class="inline-flex p-1 bg-gray-100 rounded-xl">
                        <button type="button" 
                                wire:click="setChartPeriod('day')" 
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $chartPeriod === 'day' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ __('Day') }}
                        </button>
                        <button type="button" 
                                wire:click="setChartPeriod('week')" 
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $chartPeriod === 'week' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ __('Week') }}
                        </button>
                        <button type="button" 
                                wire:click="setChartPeriod('month')" 
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $chartPeriod === 'month' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ __('Month') }}
                        </button>
                        <button type="button" 
                                wire:click="setChartPeriod('year')" 
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $chartPeriod === 'year' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ __('Year') }}
                        </button>
                    </div>

                    <!-- Navigation Controls: Prev / Current / Next -->
                    <div class="inline-flex items-center gap-1 bg-gray-50 p-1 border border-gray-200/80 rounded-xl">
                        <button type="button" 
                                wire:click="previousPeriod" 
                                title="Previous Period" 
                                class="p-1.5 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-white transition shadow-2xs">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button type="button" 
                                wire:click="goToCurrentPeriod" 
                                class="px-2.5 py-1 text-[11px] font-bold text-gray-700 hover:text-indigo-600 hover:bg-white rounded-lg transition">
                            {{ __('Current') }}
                        </button>
                        <button type="button" 
                                wire:click="nextPeriod" 
                                title="Next Period" 
                                class="p-1.5 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-white transition shadow-2xs">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chart Stats Sub-row -->
            <div class="flex items-center justify-between flex-wrap gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 font-medium">{{ __('Period Total:') }}</span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200/50">
                        +{{ $chartData['totalPeriodCount'] }} {{ $chartData['totalPeriodCount'] === 1 ? __('New User') : __('New Users') }}
                    </span>
                </div>
                <div class="text-[11px] text-gray-400 font-medium">
                    {{ __('Peak interval:') }} <span class="font-bold text-gray-700">{{ $chartData['maxCount'] }} {{ __('users') }}</span>
                </div>
            </div>

            <!-- Interactive Responsive Chart Grid -->
            <div class="pt-2">
                <div class="h-44 w-full flex items-end gap-1.5 sm:gap-2 px-2 pb-2 border-b border-gray-100 overflow-x-auto">
                    @php
                        $max = max(1, $chartData['maxCount']);
                    @endphp

                    @foreach ($chartData['points'] as $point)
                        @php
                            $heightPercent = $point['count'] > 0 ? max(12, round(($point['count'] / $max) * 100)) : 5;
                        @endphp
                        <div class="flex-1 min-w-[20px] sm:min-w-[26px] h-full flex flex-col justify-end items-center group relative cursor-pointer"
                             title="{{ $point['full_label'] }}: {{ $point['count'] }} {{ __('new user(s)') }}">
                            
                            <!-- Value tooltip on hover / indicator above bar -->
                            <div class="text-[9px] font-black text-indigo-600 mb-1 {{ $point['count'] > 0 ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition">
                                {{ $point['count'] }}
                            </div>

                            <!-- Bar Graphic -->
                            <div class="w-full max-w-[32px] rounded-t-lg transition-all duration-300 {{ $point['count'] > 0 ? 'bg-gradient-to-t from-indigo-600 to-purple-500 group-hover:from-indigo-700 group-hover:to-purple-600 shadow-2xs' : 'bg-gray-100 group-hover:bg-gray-200' }} {{ $point['is_current'] ? 'ring-2 ring-indigo-400 ring-offset-1' : '' }}"
                                 style="height: {{ $heightPercent }}%;">
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- X-Axis Labels -->
                <div class="flex gap-1.5 sm:gap-2 px-2 pt-2 overflow-x-auto">
                    @foreach ($chartData['points'] as $point)
                        <div class="flex-1 min-w-[20px] sm:min-w-[26px] text-center">
                            <span class="text-[9px] sm:text-[10px] font-bold block truncate {{ $point['is_current'] ? 'text-indigo-600 font-black' : 'text-gray-400' }}"
                                  title="{{ $point['full_label'] }}">
                                {{ $point['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Filters & Order By Block (100% Width) -->
        <div class="bg-white p-4 rounded-3xl border border-gray-100 flex flex-col md:flex-row gap-3 items-center justify-between shadow-2xs w-full">
            <!-- Search Input -->
            <div class="relative w-full md:max-w-md">
                <input wire:model.live.debounce.300ms="search" 
                       type="text" 
                       placeholder="Search by name, email, or mobile..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs">
                <div class="absolute left-3.5 top-3 text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <!-- Role Filter -->
                <div class="w-full sm:w-44">
                    <select wire:model.live="roleFilter" 
                            class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs cursor-pointer">
                        <option value="">{{ __('All Roles') }}</option>
                        @foreach ($availableRoles as $role)
                            <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Order By Filter -->
                <div class="w-full sm:w-48">
                    <select wire:model.live="sortBy" 
                            class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs cursor-pointer">
                        <option value="newest">{{ __('Newest Joined') }}</option>
                        <option value="oldest">{{ __('Oldest Joined') }}</option>
                        <option value="name_asc">{{ __('Name (A – Z)') }}</option>
                        <option value="name_desc">{{ __('Name (Z – A)') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 100% Width Full-Width User Accounts List -->
        <div class="w-full space-y-3">
            @forelse ($users as $user)
                <div class="w-full bg-white p-5 rounded-2xl border border-gray-100 hover:border-indigo-100 shadow-2xs hover:shadow-xs transition duration-150 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    
                    <!-- Left: User Identity & Join Date -->
                    <div class="flex items-center gap-3.5 min-w-[260px]">
                        @php
                            $names = explode(' ', $user->name);
                            $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                        @endphp
                        <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center font-black text-xs shadow-2xs">
                            {{ $initials }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-bold text-gray-900 truncate">
                                    {{ $user->name }}
                                </h3>
                                @if ($user->id === auth()->id())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/50">
                                        {{ __('Logged In') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-400 font-medium mt-0.5 truncate">
                                {{ __('Joined') }} {{ $user->created_at ? $user->created_at->format('M d, Y • h:i A') : 'N/A' }}
                            </p>
                        </div>
                    </div>

                    <!-- Middle: Contact Details (Email & Phone) -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 text-xs text-gray-600 min-w-[280px]">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="font-medium text-gray-700 truncate">{{ $user->email }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span class="font-mono font-medium text-gray-700">{{ $user->mobile }}</span>
                        </div>
                    </div>

                    <!-- Right: Role Badges + Edit/Delete Actions -->
                    <div class="flex items-center justify-between md:justify-end gap-3.5 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-gray-50">
                        <!-- Roles Badges -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach ($user->roles as $role)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $role->name === 'saas-admin' ? 'bg-indigo-100 text-indigo-700 border border-indigo-200/20' : ( $role->name === 'turf-admin' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200/20' : ( $role->name === 'manager' ? 'bg-amber-100 text-amber-700 border border-amber-200/20' : 'bg-blue-100 text-blue-700 border border-blue-200/20')) }}">
                                    {{ $role->display_name }}
                                </span>
                            @endforeach

                            @if ($user->roles->contains('name', 'turf-admin'))
                                @if ($user->onTrial())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Trial · ends {{ $user->trial_ends_at->format('d M') }}
                                    </span>
                                @elseif ($user->trial_started_at !== null)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-gray-100 text-gray-500 border border-gray-200">
                                        Trial ended
                                    </span>
                                @endif
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-1.5 shrink-0 pl-2 md:border-l md:border-gray-100">
                            @if ($user->roles->contains('name', 'turf-admin'))
                                <!-- Extend Trial Button -->
                                <button wire:click="extendTrial({{ $user->id }})" 
                                        title="{{ $user->onTrial() ? 'Extend Free Trial (+30 days)' : 'Grant / Extend Free Trial' }}" 
                                        class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-xl transition cursor-pointer border border-amber-200/50 flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            @endif

                            <!-- Edit Button -->
                            <button wire:click="editUser({{ $user->id }})" 
                                    title="Edit User Profile" 
                                    class="p-2 bg-gray-50 hover:bg-indigo-50 text-gray-500 hover:text-indigo-600 rounded-xl transition cursor-pointer border border-gray-100 flex items-center justify-center">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            
                            <!-- Delete Button -->
                            @if ($user->id !== auth()->id())
                                <button wire:click="confirmDelete({{ $user->id }})" 
                                        title="Delete Account" 
                                        class="p-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl transition cursor-pointer flex items-center justify-center border border-red-100/10">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @else
                                <button disabled 
                                        title="Cannot delete your active account" 
                                        class="p-2 bg-gray-50 text-gray-300 rounded-xl cursor-not-allowed border border-gray-100 flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="w-full bg-white p-12 text-center text-xs text-gray-500 rounded-3xl border border-gray-100">
                    {{ __('No user accounts match the search or filter rules.') }}
                </div>
            @endforelse
        </div>

        @if ($users->hasPages())
            <div class="bg-white px-6 py-4 rounded-3xl border border-gray-100 shadow-2xs mt-4">
                {{ $users->links() }}
            </div>
        @endif

        <!-- Create/Edit Modal Dialog -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Modal backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="resetForm"></div>

                <!-- Modal Dialog Container -->
                <div class="bg-white rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-lg z-50 border border-gray-100">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                            <h3 class="text-base font-bold text-gray-900">
                                {{ $editingId ? __('Edit User Profile') : __('Create New User Account') }}
                            </h3>
                            <button wire:click="resetForm" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 focus:outline-none">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveUser" class="space-y-5">
                            
                            <!-- Name Field -->
                            <div>
                                <x-input-label for="name" :value="__('Full Name')" />
                                <x-text-input wire:model.live.debounce.250ms="name" id="name" type="text" class="mt-1.5 block w-full" placeholder="Sandeep Rathod" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Email Field -->
                            <div>
                                <x-input-label for="email" :value="__('Email Address')" />
                                <x-text-input wire:model.live.debounce.250ms="email" id="email" type="email" class="mt-1.5 block w-full" placeholder="name@example.com" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <!-- Mobile Field -->
                            <div>
                                <x-input-label for="mobile" :value="__('Mobile Number')" />
                                <x-text-input wire:model.live.debounce.250ms="mobile" id="mobile" type="text" class="mt-1.5 block w-full" placeholder="9876543210" />
                                <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                            </div>

                            <!-- Password Field -->
                            <div>
                                <x-input-label for="password" :value="__('Security Password')" />
                                <x-text-input wire:model.live.debounce.250ms="password" id="password" type="password" class="mt-1.5 block w-full" placeholder="••••••••" />
                                @if ($editingId)
                                    <p class="text-[10px] text-gray-500 mt-1.5">{{ __('Leave blank to keep current password.') }}</p>
                                @endif
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <!-- Roles Checkboxes Grid -->
                            <div>
                                <x-input-label :value="__('Assign Security Roles')" />
                                <div class="mt-2.5 grid grid-cols-2 gap-3 bg-gray-50/50 border border-gray-200 p-4 rounded-2xl">
                                    @foreach ($availableRoles as $role)
                                        <label class="inline-flex items-center select-none {{ $role->name === 'customer' ? 'cursor-not-allowed opacity-70' : 'cursor-pointer' }}">
                                            <input type="checkbox" 
                                                   wire:model.live="selectedRoles" 
                                                   value="{{ $role->name }}" 
                                                   {{ $role->name === 'customer' ? 'disabled' : '' }}
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 {{ $role->name === 'customer' ? 'cursor-not-allowed text-indigo-400 opacity-80' : '' }}">
                                            <span class="ms-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider">{{ $role->display_name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('selectedRoles')" class="mt-2" />
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button" wire:click="resetForm" class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 transition duration-150 cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                    {{ __('Save Details') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Delete Confirmation Modal -->
        @if ($showDeleteConfirm)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="cancelDelete"></div>

                <!-- Modal Container -->
                <div class="bg-white rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-md z-50 border border-gray-100">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center gap-4 text-red-600 mb-4">
                            <div class="h-12 w-12 rounded-2xl bg-red-50 flex items-center justify-center border border-red-100/50 shrink-0">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">
                                    {{ __('Confirm Delete') }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('This action cannot be undone.') }}
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-600 mb-6 leading-relaxed">
                            {{ __('Are you sure you want to delete this user? All their role mappings will be removed.') }}
                        </p>

                        <!-- Form Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <button type="button" wire:click="cancelDelete" class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 transition duration-150 cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" wire:click="performDelete" class="px-5 py-2.5 bg-red-600 hover:bg-red-750 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                {{ __('Yes, Delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

</div>
