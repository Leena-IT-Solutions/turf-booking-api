@php
    $setting = \App\Models\SaasSetting::first();
    $logoUrl = ($setting && $setting->logo_path) ? \Illuminate\Support\Facades\Storage::url($setting->logo_path) : asset('images/logo.png');
@endphp

<img src="{{ $logoUrl }}" {{ $attributes->merge(['class' => 'h-8 w-auto object-contain rounded-lg']) }} alt="{{ config('app.name', 'TurfBooking') }}" />
