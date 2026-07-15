<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Account Settings') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="sm:px-6 lg:px-8 space-y-8 max-w-5xl">
            <!-- Profile Info Card -->
            <div class="p-6 sm:p-10 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/50 shadow-sm rounded-3xl">
                <div class="max-w-2xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <!-- Password Card -->
            <div class="p-6 sm:p-10 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/50 shadow-sm rounded-3xl">
                <div class="max-w-2xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <!-- Delete Account Card -->
            <div class="p-6 sm:p-10 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/50 shadow-sm rounded-3xl">
                <div class="max-w-2xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
