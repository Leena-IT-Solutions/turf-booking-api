@props(['active' => 'branding'])

<div class="bg-white p-2 rounded-2xl border border-gray-100 shadow-xs">
    <nav class="flex flex-wrap items-center gap-1.5" aria-label="Settings Tabs">
        <!-- Branding Tab -->
        <a href="{{ route('saas.settings.branding') }}" wire:navigate 
           class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition duration-150 ease-in-out {{ $active === 'branding' ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4 {{ $active === 'branding' ? 'text-white' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>{{ __('Branding') }}</span>
        </a>

        <!-- Legal Tab -->
        <a href="{{ route('saas.settings.legal') }}" wire:navigate 
           class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition duration-150 ease-in-out {{ $active === 'legal' ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4 {{ $active === 'legal' ? 'text-white' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>{{ __('Legal') }}</span>
        </a>

        <!-- Application Tab -->
        <a href="{{ route('saas.settings.application') }}" wire:navigate 
           class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition duration-150 ease-in-out {{ $active === 'application' ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4 {{ $active === 'application' ? 'text-white' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>{{ __('Application') }}</span>
        </a>

        <!-- Credentials Tab -->
        <a href="{{ route('saas.settings.credentials') }}" wire:navigate 
           class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition duration-150 ease-in-out {{ $active === 'credentials' ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4 {{ $active === 'credentials' ? 'text-white' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            <span>{{ __('Credentials') }}</span>
        </a>
    </nav>
</div>
