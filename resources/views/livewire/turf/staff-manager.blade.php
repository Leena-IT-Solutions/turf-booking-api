<?php

use App\Models\User;
use App\Models\StaffMember;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Search properties
    public $searchQuery = '';
    public $foundUser = null;
    public $selectedRoles = [
        'manager' => true,
        'turf-admin' => false,
    ];

    // UI feedback
    public $message = '';
    public $messageType = 'success'; // 'success' or 'error'

    // Validation rules
    protected $rules = [
        'searchQuery' => 'required|string',
    ];

    public function search()
    {
        $this->validate();
        $this->message = '';
        $this->foundUser = null;

        // Search by exact email or mobile number
        $user = User::where('email', trim($this->searchQuery))
            ->orWhere('mobile', trim($this->searchQuery))
            ->first();

        if (!$user) {
            $this->message = __('No registered user found with this email or mobile number.');
            $this->messageType = 'error';
            return;
        }

        if ($user->id === auth()->id()) {
            $this->message = __('You cannot add yourself to your own staff.');
            $this->messageType = 'error';
            return;
        }

        $this->foundUser = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'initials' => collect(explode(' ', $user->name))->map(fn($n) => mb_substr($n, 0, 1))->take(2)->join(''),
        ];
    }

    public function addStaff()
    {
        if (!$this->foundUser) {
            return;
        }

        $userId = $this->foundUser['id'];

        $rolesToAssign = collect($this->selectedRoles)
            ->filter(fn($val) => (bool)$val)
            ->keys();

        if ($rolesToAssign->isEmpty()) {
            $this->message = __('Please select at least one role.');
            $this->messageType = 'error';
            return;
        }

        $assignedCount = 0;
        $user = User::findOrFail($userId);

        foreach ($rolesToAssign as $role) {
            // Check if already assigned under this Turf Admin
            $exists = StaffMember::where('turf_admin_id', auth()->id())
                ->where('user_id', $userId)
                ->where('role', $role)
                ->exists();

            if (!$exists) {
                // Create assignment
                StaffMember::create([
                    'turf_admin_id' => auth()->id(),
                    'user_id' => $userId,
                    'role' => $role,
                ]);

                // Assign global Spatie role
                $user->assignRole($role);
                $assignedCount++;
            }
        }

        if ($assignedCount > 0) {
            $this->message = __('Staff member appointed with the selected roles successfully.');
            $this->messageType = 'success';
        } else {
            $this->message = __('This user already has all the selected roles on your staff list.');
            $this->messageType = 'error';
        }

        // Reset search states
        $this->foundUser = null;
        $this->searchQuery = '';
        $this->selectedRoles = [
            'manager' => true,
            'turf-admin' => false,
        ];
    }

    public function revokeStaff($assignmentId)
    {
        $assignment = StaffMember::where('turf_admin_id', auth()->id())
            ->findOrFail($assignmentId);

        $userId = $assignment->user_id;
        $role = $assignment->role;

        // Delete association
        $assignment->delete();

        // Check if user is still assigned to this role under any OTHER Turf Admin
        $otherAssignments = StaffMember::where('user_id', $userId)
            ->where('role', $role)
            ->exists();

        if (!$otherAssignments) {
            // No other assignments found, remove global role
            $user = User::findOrFail($userId);
            $user->retractRole($role);
        }

        $this->message = __('Selected staff privilege revoked successfully.');
        $this->messageType = 'success';
    }

    public function revokeAllStaff($userId)
    {
        $assignments = StaffMember::where('turf_admin_id', auth()->id())
            ->where('user_id', $userId)
            ->get();

        foreach ($assignments as $assignment) {
            $role = $assignment->role;
            $assignment->delete();

            // Check other assignments
            $other = StaffMember::where('user_id', $userId)
                ->where('role', $role)
                ->exists();

            if (!$other) {
                $user = User::findOrFail($userId);
                $user->retractRole($role);
            }
        }

        $this->message = __('All staff privileges revoked from this user.');
        $this->messageType = 'success';
    }

    public function with()
    {
        // Load active staff members for this Turf Admin, grouped by user
        $staffGrouped = StaffMember::where('turf_admin_id', auth()->id())
            ->with('user')
            ->get()
            ->groupBy('user_id');

        return [
            'staffGrouped' => $staffGrouped,
        ];
    }
}; ?>

<div x-data="{
    confirmOpen: false,
    confirmTitle: '',
    confirmMessage: '',
    confirmAction: null,
    confirmId: null,
    triggerConfirm(title, message, action, id) {
        this.confirmTitle = title;
        this.confirmMessage = message;
        this.confirmAction = action;
        this.confirmId = id;
        this.confirmOpen = true;
    },
    executeAction() {
        this.confirmOpen = false;
        if (this.confirmAction === 'revokeStaff') {
            $wire.revokeStaff(this.confirmId);
        } else if (this.confirmAction === 'revokeAllStaff') {
            $wire.revokeAllStaff(this.confirmId);
        }
    }
}" class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-violet-600 to-indigo-600 p-8 rounded-3xl text-white shadow-md relative overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-2xl font-extrabold tracking-tight">{{ __('Admin & Manager Management') }}</h2>
                <p class="text-xs text-indigo-100 mt-2 max-w-xl leading-relaxed">
                    {{ __('Appoint managers and administrators to help you run and organize your turfs. Appointed staff will have access to specific features in the mobile application.') }}
                </p>
            </div>
            <div class="absolute -right-10 -bottom-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        </div>

        <!-- Alerts -->
        @if ($message)
            <div class="p-4 rounded-2xl flex items-center gap-3 border shadow-sm transition-all {{ $messageType === 'success' ? 'bg-emerald-50 border-emerald-100 dark:bg-emerald-950/20 dark:border-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-red-50 border-red-100 dark:bg-red-950/20 dark:border-red-950 text-red-800 dark:text-red-300' }}">
                @if ($messageType === 'success')
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z" />
                    </svg>
                @else
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                @endif
                <span class="text-sm font-medium">{{ $message }}</span>
            </div>
        @endif

        <!-- Search & Appointment Workspace -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Search card -->
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ __('Find User') }}</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Search registered customers by email or mobile number.') }}</p>
                </div>

                <form wire:submit.prevent="search" class="space-y-4">
                    <div>
                        <label for="searchQuery" class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Email / Mobile') }}</label>
                        <div class="relative">
                            <input type="text" id="searchQuery" wire:model="searchQuery" placeholder="e.g. sandeep198558@gmail.com" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" required />
                        </div>
                        @error('searchQuery') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-md shadow-indigo-500/10 hover:shadow-indigo-500/20 active:scale-[0.98] transition cursor-pointer flex items-center justify-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        {{ __('Search') }}
                    </button>
                </form>

                <!-- Search Result Panel -->
                @if ($foundUser)
                    <div class="p-5 bg-indigo-50/50 dark:bg-indigo-950/10 border border-indigo-100/50 dark:border-indigo-950 rounded-2xl space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-500/20">
                                {{ $foundUser['initials'] }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $foundUser['name'] }}</h4>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ $foundUser['email'] }}</p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ $foundUser['mobile'] }}</p>
                            </div>
                        </div>

                        <hr class="border-indigo-100/50 dark:border-indigo-950" />

                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ __('Select Roles') }}</label>
                                <div class="space-y-2.5">
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" wire:model="selectedRoles.manager" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="font-medium">{{ __('Manager') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" wire:model="selectedRoles.turf-admin" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="font-medium">{{ __('Admin') }}</span>
                                    </label>
                                </div>
                            </div>

                            <button wire:click="addStaff" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition cursor-pointer">
                                {{ __('Add Staff Member') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Active Staff cards grid -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between px-2">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ __('Current Staff Members') }}</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('List of admins and managers managing your venue.') }}</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-bold bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                        {{ $staffGrouped->count() }} {{ trans_choice('Member|Members', $staffGrouped->count()) }}
                    </span>
                </div>

                @if ($staffGrouped->isEmpty())
                    <div class="bg-white dark:bg-gray-800 border border-dashed border-gray-200 dark:border-gray-700 p-12 rounded-3xl flex flex-col items-center justify-center text-center">
                        <span class="text-4xl">👥</span>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 mt-4">{{ __('No Staff Appointed') }}</h4>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm">
                            {{ __('You haven\'t assigned any manager or admin roles yet. Search for users by their email or mobile number to appoint them.') }}
                        </p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($staffGrouped as $userId => $group)
                            @php
                                $user = $group->first()->user;
                            @endphp
                            <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 transition hover:shadow-md relative overflow-hidden group">
                                <div class="flex items-center gap-4">
                                    <div class="h-12 w-12 shrink-0 rounded-xl bg-gradient-to-tr from-indigo-50 to-indigo-100/50 dark:from-indigo-950/20 dark:to-indigo-900/10 border border-indigo-100/30 dark:border-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-extrabold text-base shadow-sm">
                                        {{ collect(explode(' ', $user->name))->map(fn($n) => mb_substr($n, 0, 1))->take(2)->join('') }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center flex-wrap gap-2">
                                            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $user->name }}</h4>
                                            
                                            <!-- Grouped roles list inside user card -->
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($group as $member)
                                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider rounded-md {{ $member->role === 'turf-admin' ? 'bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 border border-blue-100/50 dark:border-blue-950' : 'bg-purple-50 dark:bg-purple-950/20 text-purple-600 dark:text-purple-400 border border-purple-100/50 dark:border-purple-950' }}">
                                                        {{ $member->role === 'turf-admin' ? __('Admin') : __('Manager') }}
                                                        <button @click.prevent="triggerConfirm('{{ __('Revoke Role') }}', '{{ __('Are you sure you want to revoke this specific role from this user?') }}', 'revokeStaff', {{ $member->id }})" title="{{ __('Revoke role') }}" class="ms-1 hover:text-red-500 font-bold transition cursor-pointer">
                                                            ✕
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                            <span class="flex items-center gap-1">
                                                ✉️ {{ $user->email }}
                                            </span>
                                            <span class="hidden sm:inline text-gray-300 dark:text-gray-700">•</span>
                                            <span class="flex items-center gap-1">
                                                📞 {{ $user->mobile }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end shrink-0 border-t sm:border-t-0 border-gray-50 dark:border-gray-850/50 pt-3 sm:pt-0">
                                    <button @click.prevent="triggerConfirm('{{ __('Revoke All Roles') }}', '{{ __('Are you sure you want to revoke all staff privileges from this user?') }}', 'revokeAllStaff', {{ $user->id }})" class="text-xs font-semibold text-red-500 hover:text-red-650 cursor-pointer flex items-center gap-1.5 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        {{ __('Revoke All') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    </div>

    <!-- Confirmation Modal -->
    <div x-show="confirmOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
         style="display: none;">
        
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" @click="confirmOpen = false"></div>

        <!-- Modal Content -->
        <div x-show="confirmOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative bg-white dark:bg-gray-800 rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 dark:border-gray-700/50 space-y-6 z-10 text-center sm:text-left">
            
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4">
                <div class="h-12 w-12 rounded-2xl bg-red-50 dark:bg-red-950/30 text-red-500 flex items-center justify-center shrink-0 border border-red-100 dark:border-red-900/30">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="space-y-2 min-w-0 flex-1">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-gray-100" x-text="confirmTitle"></h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 leading-relaxed" x-text="confirmMessage"></p>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-2.5 pt-2">
                <button type="button" @click="confirmOpen = false" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition cursor-pointer">
                    {{ __('Cancel') }}
                </button>
                <button type="button" @click="executeAction" class="px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-md shadow-red-500/10 hover:shadow-red-500/20 transition cursor-pointer">
                    {{ __('Revoke Staff') }}
                </button>
            </div>
        </div>
    </div>
</div>
