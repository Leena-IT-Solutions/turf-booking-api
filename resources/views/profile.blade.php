<x-app-layout>
    <x-slot name="header">
        <h2 class="font-black text-2xl text-gray-900 tracking-tight flex items-center gap-2.5">
            <span class="w-2.5 h-6 rounded-full bg-indigo-600 inline-block"></span>
            {{ __('Account Settings') }}
        </h2>
    </x-slot>

    @php
        $user = auth()->user();
        $nameParts = explode(' ', trim($user->name));
        $initials = count($nameParts) >= 2 
            ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
            : strtoupper(substr($user->name, 0, 2));
        $roles = $user->roles->pluck('name')->toArray();
    @endphp

    <div class="max-w-6xl mx-auto space-y-8" x-data="{ activeTab: 'personal' }">
        
        <!-- PROFILE HERO HEADER -->
        <div class="relative overflow-hidden rounded-3xl bg-white border border-gray-100 shadow-xl shadow-gray-100/70 p-6 sm:p-8">
            <!-- Subtle background ambient mesh -->
            <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full bg-gradient-to-br from-indigo-200/40 to-purple-200/30 blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-80 h-80 rounded-full bg-gradient-to-br from-emerald-200/30 to-teal-200/20 blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <!-- User Avatar & Core Details -->
                <div class="flex items-center gap-5">
                    <div class="relative shrink-0">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-gradient-to-tr from-indigo-600 via-purple-600 to-pink-500 p-1 shadow-xl shadow-indigo-600/20">
                            <div class="w-full h-full rounded-[22px] bg-white flex items-center justify-center text-2xl sm:text-3xl font-black text-indigo-700 tracking-wider">
                                {{ $initials }}
                            </div>
                        </div>
                        <span class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-emerald-500 border-2 border-white flex items-center justify-center text-[10px] text-white font-bold shadow-sm" title="Active Account">
                            ✓
                        </span>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">
                                {{ $user->name }}
                            </h1>
                            @foreach ($roles as $role)
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/80 shadow-xs">
                                    {{ str_replace('-', ' ', $role) }}
                                </span>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center gap-4 text-xs font-semibold text-gray-500">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ $user->email }}
                            </span>
                            @if ($user->mobile)
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    {{ $user->mobile }}
                                </span>
                            @endif
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Member since {{ $user->created_at?->format('M Y') ?? '2026' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABS BAR -->
            <div class="flex items-center gap-2 border-t border-gray-100 mt-6 pt-4 overflow-x-auto">
                <button 
                    @click="activeTab = 'personal'"
                    :class="activeTab === 'personal' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/25' : 'text-gray-600 hover:bg-gray-100'"
                    type="button"
                    class="px-4 py-2 rounded-2xl text-xs font-bold transition duration-150 flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>{{ __('Personal Details') }}</span>
                </button>

                <button 
                    @click="activeTab = 'security'"
                    :class="activeTab === 'security' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/25' : 'text-gray-600 hover:bg-gray-100'"
                    type="button"
                    class="px-4 py-2 rounded-2xl text-xs font-bold transition duration-150 flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>{{ __('Security & Password') }}</span>
                </button>

                <button 
                    @click="activeTab = 'danger'"
                    :class="activeTab === 'danger' ? 'bg-rose-600 text-white shadow-md shadow-rose-600/25' : 'text-gray-600 hover:bg-gray-100'"
                    type="button"
                    class="px-4 py-2 rounded-2xl text-xs font-bold transition duration-150 flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>{{ __('Danger Zone') }}</span>
                </button>
            </div>
        </div>

        <!-- TAB CONTENTS -->
        <!-- Personal Information Tab -->
        <div x-show="activeTab === 'personal'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="bg-white border border-gray-100 shadow-xl shadow-gray-100/60 rounded-3xl p-6 sm:p-10">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        <!-- Security / Password Tab -->
        <div x-show="activeTab === 'security'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <div class="bg-white border border-gray-100 shadow-xl shadow-gray-100/60 rounded-3xl p-6 sm:p-10">
                <livewire:profile.update-password-form />
            </div>
        </div>

        <!-- Danger Zone Tab -->
        <div x-show="activeTab === 'danger'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <div class="bg-white border-2 border-rose-200/70 shadow-xl shadow-rose-100/40 rounded-3xl p-6 sm:p-10">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
