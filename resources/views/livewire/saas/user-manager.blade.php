<?php

use App\Models\Role;
use App\Models\User;
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

    // Form inputs
    public $name = '';
    public $email = '';
    public $mobile = '';
    public $password = '';
    public $selectedRoles = [];

    // State
    public $editingId = null;
    public $showModal = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
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

            // Sync roles
            $user->roles()->sync(
                Role::whereIn('name', $this->selectedRoles)->pluck('id')->toArray()
            );

            session()->flash('status', 'User details updated successfully.');
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'mobile' => $this->mobile,
                'password' => Hash::make($this->password),
            ]);

            // Sync roles
            $user->roles()->sync(
                Role::whereIn('name', $this->selectedRoles)->pluck('id')->toArray()
            );

            session()->flash('status', 'User created successfully.');
        }

        $this->resetForm();
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

    public function with()
    {
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

        return [
            'users' => $query->orderBy('name', 'asc')->paginate(10),
            'availableRoles' => Role::all(),
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('User Account Management') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Administer global platform accounts, configure credentials, and assign multi-role accessibility details.') }}</p>
            </div>
            <div>
                <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    {{ __('Add New User') }}
                </button>
            </div>
        </div>

        @if (session()->has('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-rose-50 dark:bg-rose-955/20 border border-rose-100 dark:border-rose-900/40 text-rose-800 dark:text-rose-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Filters Block -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="relative w-full md:max-w-xs">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, email, or mobile..." class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-xs bg-gray-50/50 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                <div class="absolute left-3.5 top-3 text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div class="w-full md:w-48">
                <select wire:model.live="roleFilter" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-xs bg-gray-50/50 dark:bg-gray-900/30 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">{{ __('All Roles') }}</option>
                    @foreach ($availableRoles as $role)
                        <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden border border-gray-100 dark:border-gray-700/50 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/50">
                    <thead class="bg-gray-50/50 dark:bg-gray-900/40">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('User Name') }}</th>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Contact Info') }}</th>
                            <th scope="col" class="px-6 py-4 text-left text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Roles') }}</th>
                            <th scope="col" class="px-6 py-4 class text-left text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Registration Date') }}</th>
                            <th scope="col" class="relative px-6 py-4 text-right text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/40">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/20 transition duration-150">
                                <td class="px-6 py-4.5 whitespace-nowrap">
                                    <div class="flex items-center gap-3.5">
                                        <div class="h-10 w-10 shrink-0 rounded-xl bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white flex items-center justify-center font-black text-xs shadow-sm">
                                            @php
                                                $names = explode(' ', $user->name);
                                                $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                                            @endphp
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                                            @if ($user->id === auth()->id())
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 mt-1">
                                                    {{ __('You') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4.5 whitespace-nowrap text-xs">
                                    <div class="text-gray-900 dark:text-gray-100 font-medium">{{ $user->email }}</div>
                                    <div class="text-gray-400 dark:text-gray-500 mt-0.5 font-mono">{{ $user->mobile }}</div>
                                </td>
                                <td class="px-6 py-4.5 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1.5 max-w-xs">
                                        @foreach ($user->roles as $role)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider shadow-inner {{
                                                $role->name === 'saas-admin' ? 'bg-indigo-100 dark:bg-indigo-955/20 text-indigo-700 dark:text-indigo-400' : (
                                                $role->name === 'turf-admin' ? 'bg-emerald-100 dark:bg-emerald-955/20 text-emerald-700 dark:text-emerald-400' : (
                                                $role->name === 'manager' ? 'bg-amber-100 dark:bg-amber-955/20 text-amber-700 dark:text-amber-400' :
                                                'bg-blue-100 dark:bg-blue-955/20 text-blue-700 dark:text-blue-400'))
                                            }}">
                                                {{ $role->display_name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4.5 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4.5 whitespace-nowrap text-right text-xs font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="editUser({{ $user->id }})" class="p-2 bg-white hover:bg-gray-50 text-indigo-600 rounded-xl shadow-md transition transform hover:scale-105 cursor-pointer flex items-center justify-center border border-gray-100 dark:border-gray-800">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                        
                                        @if ($user->id !== auth()->id())
                                            <button onclick="confirm('Are you sure you want to delete this user? All their role mappings will be removed.') || event.stopImmediatePropagation()" wire:click="deleteUser({{ $user->id }})" class="p-2 bg-red-600 hover:bg-red-700 text-white rounded-xl shadow-md transition transform hover:scale-105 cursor-pointer flex items-center justify-center">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @else
                                            <button disabled class="p-2 bg-gray-100 dark:bg-gray-800 text-gray-300 dark:text-gray-600 rounded-xl cursor-not-allowed flex items-center justify-center">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('No user accounts match the search or filter rules.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700/50">
                    {{ $users->links() }}
                </div>
            @endif
        </div>

        <!-- Create/Edit Modal Dialog -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Modal backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="resetForm"></div>

                <!-- Modal Dialog Container -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-lg z-50 border border-gray-100 dark:border-gray-700">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50 mb-6">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ $editingId ? __('Edit User Profile') : __('Create New User Account') }}
                            </h3>
                            <button wire:click="resetForm" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
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
                                <x-text-input wire:model.live.debounce.250ms="mobile" id="mobile" type="text" class="mt-1.5 block w-full" placeholder="9664588677" />
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
                                <div class="mt-2.5 grid grid-cols-2 gap-3 bg-gray-50/50 dark:bg-gray-900/30 border border-gray-150 dark:border-gray-700/60 p-4 rounded-2xl">
                                    @foreach ($availableRoles as $role)
                                        <label class="inline-flex items-center select-none cursor-pointer">
                                            <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role->name }}" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="ms-2.5 text-xs font-bold text-gray-700 dark:text-gray-350 uppercase tracking-wider">{{ $role->display_name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('selectedRoles')" class="mt-2" />
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" wire:click="resetForm" class="px-5 py-2.5 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 rounded-xl font-bold text-xs uppercase text-gray-700 dark:text-gray-300 transition duration-150 cursor-pointer">
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

    </div>
</div>
