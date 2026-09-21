@props(['active' => 'terms'])

@php
    $policies = [
        'terms' => [
            'label' => 'Terms & Conditions',
            'route' => 'terms-and-conditions',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        'privacy' => [
            'label' => 'Privacy Policy',
            'route' => 'privacy-policy',
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        ],
        'cancellation' => [
            'label' => 'Refund & Cancellation',
            'route' => 'refund-cancellation-policy',
            'icon' => 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z',
        ],
        'return' => [
            'label' => 'Return Policy',
            'route' => 'return-policy',
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        ],
        'shipping' => [
            'label' => 'Shipping Policy',
            'route' => 'shipping-policy',
            'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
        ],
    ];
@endphp

<div class="w-full mb-8">
    <!-- Desktop / Tablet Pill Switcher -->
    <div class="hidden sm:flex items-center gap-1.5 p-1.5 bg-slate-100/80 backdrop-blur-md rounded-2xl border border-slate-200/80 overflow-x-auto">
        @foreach ($policies as $key => $item)
            @php $isActive = ($active === $key); @endphp
            <a href="{{ route($item['route']) }}"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $isActive ? 'bg-white text-emerald-600 shadow-sm border border-slate-200/60' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
                <svg class="w-4 h-4 {{ $isActive ? 'text-emerald-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                </svg>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    <!-- Mobile Dropdown / Horizontal Scroll -->
    <div class="sm:hidden flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
        @foreach ($policies as $key => $item)
            @php $isActive = ($active === $key); @endphp
            <a href="{{ route($item['route']) }}"
                class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap {{ $isActive ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200' }}">
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
