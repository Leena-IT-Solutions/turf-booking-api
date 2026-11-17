<?php

use App\Models\NotificationDelivery;
use App\Models\NotificationSetting;
use App\Models\SaasSetting;
use App\Services\NotificationService;
use App\Support\NotificationEvents;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Legacy properties kept for backward compatibility
    public $notify_booking_created = true;

    public $notify_booking_cancelled = true;

    public $notify_payment_received = true;

    public $notify_booking_rescheduled = true;

    public $fcm_project_id = '';

    public $fcm_service_account_json = '';

    /** @var array<string, bool> */
    public array $pushEnabled = [];

    /** @var array<string, bool> */
    public array $emailEnabled = [];

    public int $reminderLeadMinutes = 60;

    public ?string $savedNotice = null;

    public function mount(): void
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
        ]);

        $this->notify_booking_created = (bool) ($setting->notify_booking_created ?? true);
        $this->notify_booking_cancelled = (bool) ($setting->notify_booking_cancelled ?? true);
        $this->notify_payment_received = (bool) ($setting->notify_payment_received ?? true);
        $this->notify_booking_rescheduled = (bool) ($setting->notify_booking_rescheduled ?? true);
        $this->fcm_project_id = $setting->fcm_project_id ?? '';
        $this->fcm_service_account_json = $setting->fcm_service_account_json ?? '';

        $this->refreshMatrixState();
    }

    public function refreshMatrixState(): void
    {
        NotificationSetting::forgetCache();

        foreach (NotificationEvents::all() as $key => $def) {
            $this->pushEnabled[$key] = NotificationSetting::isEnabled($key, 'push');
            if (in_array('email', $def['channels'], true)) {
                $this->emailEnabled[$key] = NotificationSetting::isEnabled($key, 'email');
            }
        }

        $this->reminderLeadMinutes = (int) NotificationSetting::configValue('customer.match_reminder', 'lead_minutes', 60);
    }

    public function toggleEvent(string $eventKey, string $channel = 'push'): void
    {
        $def = NotificationEvents::get($eventKey);
        if (! $def || ! in_array($channel, $def['channels'], true)) {
            return;
        }

        $row = NotificationSetting::firstOrNew(['event_key' => $eventKey]);
        if (! $row->exists) {
            $row->push_enabled = NotificationSetting::isEnabled($eventKey, 'push');
            $row->email_enabled = in_array('email', $def['channels'], true)
                ? NotificationSetting::isEnabled($eventKey, 'email')
                : null;
            $row->config = $def['default_config'] ?? null;
        }

        if ($channel === 'email') {
            $row->email_enabled = ! (bool) ($row->email_enabled ?? true);
            $newState = $row->email_enabled;
        } else {
            $row->push_enabled = ! (bool) ($row->push_enabled ?? true);
            $newState = $row->push_enabled;
        }

        $row->updated_by = auth()->id();
        $row->save();

        // Keep legacy SaasSetting boolean in sync if this is a customer legacy event
        if ($channel === 'push' && ! empty($def['legacy_saas_column']) && str_starts_with($eventKey, 'customer.')) {
            $col = $def['legacy_saas_column'];
            $this->{$col} = $newState;
            SaasSetting::query()->update([$col => $newState]);
            SaasSetting::forgetCache();
        }

        $this->refreshMatrixState();
        $this->savedNotice = sprintf(
            '%s (%s) %s.',
            $def['label'],
            strtoupper($channel),
            $newState ? 'enabled' : 'disabled'
        );
    }

    public function setCategoryEnabled(string $category, bool $enabled): void
    {
        $events = NotificationEvents::byCategory()[$category] ?? [];
        if (empty($events)) {
            return;
        }

        foreach ($events as $eventKey => $def) {
            $row = NotificationSetting::firstOrNew(['event_key' => $eventKey]);
            $row->push_enabled = $enabled;
            if (in_array('email', $def['channels'], true)) {
                $row->email_enabled = $enabled;
            }
            if (! $row->exists && ! empty($def['default_config'])) {
                $row->config = $def['default_config'];
            }
            $row->updated_by = auth()->id();
            $row->save();
        }

        $this->refreshMatrixState();
        $this->savedNotice = sprintf(
            'All %s notifications %s.',
            ucfirst($category),
            $enabled ? 'enabled' : 'disabled'
        );
    }

    public function updatedReminderLeadMinutes($value): void
    {
        $minutes = (int) $value;
        $allowed = array_keys(NotificationEvents::reminderLeadTimeOptions());
        if (! in_array($minutes, $allowed, true)) {
            $minutes = 60;
        }

        $this->reminderLeadMinutes = $minutes;

        $row = NotificationSetting::firstOrNew(['event_key' => 'customer.match_reminder']);
        if (! $row->exists) {
            $row->push_enabled = NotificationSetting::isEnabled('customer.match_reminder', 'push');
            $row->email_enabled = null;
        }
        $config = is_array($row->config) ? $row->config : [];
        $config['lead_minutes'] = $minutes;
        $row->config = $config;
        $row->updated_by = auth()->id();
        $row->save();

        $this->refreshMatrixState();
        $label = NotificationEvents::reminderLeadTimeOptions()[$minutes] ?? "{$minutes} minutes before";
        $this->savedNotice = "Match Reminder lead time saved ({$label}).";
    }

    public function sendTestPush(string $eventKey): void
    {
        $def = NotificationEvents::get($eventKey);
        $admin = auth()->user();
        if (! $def || ! $admin) {
            return;
        }

        NotificationService::dispatchPush(
            [$admin->id],
            'test.'.$eventKey,
            '[TEST] '.$def['sample_title'],
            $def['sample_body'],
            array_merge($def['sample_data'], [
                'android_channel_id' => $def['android_channel_id'] ?? 'bookings',
            ])
        );

        $this->savedNotice = "Test push for \"{$def['label']}\" dispatched to your registered device(s).";
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, [
            'notify_booking_created',
            'notify_booking_cancelled',
            'notify_payment_received',
            'notify_booking_rescheduled',
            'fcm_project_id',
            'fcm_service_account_json',
        ], true)) {
            $this->validateOnly($propertyName, [
                'notify_booking_created' => 'boolean',
                'notify_booking_cancelled' => 'boolean',
                'notify_payment_received' => 'boolean',
                'notify_booking_rescheduled' => 'boolean',
                'fcm_project_id' => 'nullable|string|max:255',
                'fcm_service_account_json' => 'nullable|string',
            ]);
        }
    }

    public function saveSettings(): void
    {
        $this->validate([
            'notify_booking_created' => 'boolean',
            'notify_booking_cancelled' => 'boolean',
            'notify_payment_received' => 'boolean',
            'notify_booking_rescheduled' => 'boolean',
            'fcm_project_id' => 'nullable|string|max:255',
            'fcm_service_account_json' => 'nullable|string',
        ]);

        $setting = SaasSetting::first() ?? new SaasSetting;

        $setting->fill([
            'notify_booking_created' => $this->notify_booking_created,
            'notify_booking_cancelled' => $this->notify_booking_cancelled,
            'notify_payment_received' => $this->notify_payment_received,
            'notify_booking_rescheduled' => $this->notify_booking_rescheduled,
            'fcm_project_id' => $this->fcm_project_id,
            'fcm_service_account_json' => $this->fcm_service_account_json,
        ])->save();

        $this->refreshMatrixState();

        session()->flash('status', __('Push notification settings and credentials updated successfully.'));
    }

    public function with(): array
    {
        $allEvents = NotificationEvents::all();
        $totalEventsCount = count($allEvents);
        $activeEventsCount = 0;

        foreach ($allEvents as $key => $def) {
            $pushOn = $this->pushEnabled[$key] ?? true;
            $emailOn = in_array('email', $def['channels'], true) ? ($this->emailEnabled[$key] ?? true) : false;
            if ($pushOn || $emailOn) {
                $activeEventsCount++;
            }
        }

        $since = now()->subDay();
        $stats24h = [
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'no_device' => 0,
        ];

        try {
            $counts = NotificationDelivery::where('created_at', '>=', $since)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $stats24h['sent'] = (int) ($counts[NotificationDelivery::STATUS_SENT] ?? 0);
            $stats24h['skipped'] = (int) ($counts[NotificationDelivery::STATUS_SKIPPED_DISABLED] ?? 0)
                + (int) ($counts[NotificationDelivery::STATUS_SKIPPED_OPT_OUT] ?? 0);
            $stats24h['failed'] = (int) ($counts[NotificationDelivery::STATUS_FAILED] ?? 0);
            $stats24h['no_device'] = (int) ($counts[NotificationDelivery::STATUS_NO_DEVICE] ?? 0);
        } catch (\Throwable $e) {
            // Table may not exist during initial migration
        }

        return [
            'categories' => NotificationEvents::byCategory(),
            'leadTimeOptions' => NotificationEvents::reminderLeadTimeOptions(),
            'totalEventsCount' => $totalEventsCount,
            'activeEventsCount' => $activeEventsCount,
            'stats24h' => $stats24h,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Top Tab Bar -->
    @include('livewire.saas.settings.tabs', ['active' => 'notifications'])

    <!-- Header & Summary Metrics Card -->
    <div class="bg-white p-6 shadow-sm rounded-3xl border border-gray-100 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Push & Email Notification Control Center') }}</h2>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                            {{ $activeEventsCount }} {{ __('of') }} {{ $totalEventsCount }} {{ __('Active') }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ __('All event switches auto-save immediately. Configure Customer, Venue, and Admin alerts per channel.') }}</p>
                </div>
            </div>
        </div>

        <!-- Last 24h Delivery Telemetry -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-gray-100">
            <div class="p-3.5 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">{{ __('Sent (Last 24h)') }}</p>
                <p class="text-lg font-extrabold text-emerald-900 mt-0.5">{{ number_format($stats24h['sent']) }}</p>
            </div>
            <div class="p-3.5 rounded-2xl bg-amber-50/60 border border-amber-100">
                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">{{ __('Skipped (Disabled / Opt-Out)') }}</p>
                <p class="text-lg font-extrabold text-amber-900 mt-0.5">{{ number_format($stats24h['skipped']) }}</p>
            </div>
            <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-200/70">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600">{{ __('No Device Registered') }}</p>
                <p class="text-lg font-extrabold text-gray-800 mt-0.5">{{ number_format($stats24h['no_device']) }}</p>
            </div>
            <div class="p-3.5 rounded-2xl bg-rose-50/60 border border-rose-100">
                <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">{{ __('Failed (Last 24h)') }}</p>
                <p class="text-lg font-extrabold text-rose-900 mt-0.5">{{ number_format($stats24h['failed']) }}</p>
            </div>
        </div>
    </div>

    @if ($savedNotice)
        <div class="bg-indigo-50 border border-indigo-100 text-indigo-900 px-5 py-3.5 rounded-2xl text-xs font-bold flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $savedNotice }}</span>
            </div>
            <button type="button" wire:click="$set('savedNotice', null)" class="text-indigo-500 hover:text-indigo-700 text-xs font-bold uppercase">
                {{ __('Dismiss') }}
            </button>
        </div>
    @endif

    @if (session()->has('status'))
        <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-5 py-3.5 rounded-2xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
            <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @php
        $categoryMeta = [
            'customer' => [
                'title' => __('Customer (Player) Notifications'),
                'subtitle' => __('Booking confirmations, cancellations, refunds, match reminders, post-match reviews, support replies, and targeted offers.'),
                'badge_bg' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            ],
            'venue' => [
                'title' => __('Turf Owner & Venue Staff Notifications'),
                'subtitle' => __('Scoped strictly to each turf\'s owner and their assigned staff. SaaS admins do not receive venue booking alerts.'),
                'badge_bg' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
            ],
            'admin' => [
                'title' => __('SaaS Admin & Marketing Executive Alerts'),
                'subtitle' => __('Platform-level operational alerts for new turf onboarding submissions, customer support messages, and payouts.'),
                'badge_bg' => 'bg-amber-50 text-amber-700 border-amber-100',
            ],
        ];
    @endphp

    <!-- 3 Categorized Notification Cards -->
    @foreach ($categories as $categoryKey => $events)
        @php
            $meta = $categoryMeta[$categoryKey];
        @endphp
        <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
            <div class="pb-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900 tracking-tight">{{ $meta['title'] }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ $meta['subtitle'] }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        wire:click="setCategoryEnabled('{{ $categoryKey }}', true)"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-100 transition cursor-pointer"
                    >
                        {{ __('Enable All') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setCategoryEnabled('{{ $categoryKey }}', false)"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 transition cursor-pointer"
                    >
                        {{ __('Disable All') }}
                    </button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($events as $eventKey => $def)
                    @php
                        $pushOn = (bool) ($pushEnabled[$eventKey] ?? true);
                        $supportsEmail = in_array('email', $def['channels'], true);
                        $emailOn = $supportsEmail ? (bool) ($emailEnabled[$eventKey] ?? true) : false;
                        $isPromo = ($def['kind'] ?? '') === 'promotional';
                    @endphp
                    <div class="py-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4" wire:key="event-row-{{ $eventKey }}">
                        <div class="space-y-1.5 max-w-2xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-gray-900">{{ $def['label'] }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $meta['badge_bg'] }}">
                                    {{ $def['audience_label'] }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $isPromo ? 'bg-purple-50 text-purple-700 border-purple-100' : 'bg-gray-100 text-gray-700 border-gray-200' }}">
                                    {{ $isPromo ? __('Promotional (User Opt-Out Respected)') : __('Transactional') }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ $def['description'] }}
                            </p>

                            @if ($eventKey === 'customer.match_reminder')
                                <div class="pt-2 flex flex-wrap items-center gap-3">
                                    <label for="reminderLeadSelect" class="text-xs font-bold text-gray-700">
                                        {{ __('Send reminder:') }}
                                    </label>
                                    <select
                                        id="reminderLeadSelect"
                                        wire:model.live="reminderLeadMinutes"
                                        @disabled(! $pushOn)
                                        class="rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 text-xs font-bold text-gray-800 py-1.5 pl-3 pr-8 disabled:opacity-50"
                                    >
                                        @foreach ($leadTimeOptions as $mins => $optionLabel)
                                            <option value="{{ $mins }}">{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-[11px] text-gray-400 font-semibold">
                                        {{ __('Players will be reminded') }} {{ $leadTimeOptions[$reminderLeadMinutes] ?? ($reminderLeadMinutes . ' minutes before') }} {{ __('their first slot.') }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-4 shrink-0">
                            <!-- Push Switch -->
                            <div class="flex items-center gap-2.5 bg-gray-50/80 px-3.5 py-2 rounded-2xl border border-gray-100">
                                <span class="text-xs font-bold text-gray-700">{{ __('Push') }}</span>
                                <span class="text-[11px] font-bold {{ $pushOn ? 'text-indigo-600' : 'text-gray-400' }}">
                                    {{ $pushOn ? __('ON') : __('OFF') }}
                                </span>
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked="{{ $pushOn ? 'true' : 'false' }}"
                                    wire:click="toggleEvent('{{ $eventKey }}', 'push')"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $pushOn ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                >
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $pushOn ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                            </div>

                            <!-- Email Switch (if supported by event) -->
                            @if ($supportsEmail)
                                <div class="flex items-center gap-2.5 bg-gray-50/80 px-3.5 py-2 rounded-2xl border border-gray-100">
                                    <span class="text-xs font-bold text-gray-700">{{ __('Email') }}</span>
                                    <span class="text-[11px] font-bold {{ $emailOn ? 'text-indigo-600' : 'text-gray-400' }}">
                                        {{ $emailOn ? __('ON') : __('OFF') }}
                                    </span>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked="{{ $emailOn ? 'true' : 'false' }}"
                                        wire:click="toggleEvent('{{ $eventKey }}', 'email')"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $emailOn ? 'bg-indigo-600' : 'bg-gray-200' }}"
                                    >
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $emailOn ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </div>
                            @endif

                            <!-- Send Test Push to Me -->
                            <button
                                type="button"
                                wire:click="sendTestPush('{{ $eventKey }}')"
                                class="px-3 py-2 rounded-xl text-[11px] font-bold text-gray-600 hover:text-indigo-600 bg-white hover:bg-indigo-50 border border-gray-200 hover:border-indigo-100 transition cursor-pointer"
                                title="{{ __('Send a sample push notification to your own registered device') }}"
                            >
                                {{ __('Test Push') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <!-- Firebase Cloud Messaging Credentials Card (Explicit Save Button) -->
    <form wire:submit="saveSettings" class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
            <div>
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span>{{ __('Firebase Cloud Messaging (FCM HTTP v1) Credentials') }}</span>
                </h3>
                <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('Credentials for mobile push notifications via Google Firebase Cloud Messaging HTTP v1 API.') }}</p>
            </div>
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition duration-150 cursor-pointer disabled:opacity-50 shrink-0">
                <svg wire:loading.remove wire:target="saveSettings" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="saveSettings" class="animate-spin w-4 h-4 text-white" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>{{ __('Save FCM Credentials') }}</span>
            </button>
        </div>

        <div class="space-y-6">
            <div>
                <x-input-label for="fcmProjectId" :value="__('Firebase Project ID')" />
                <x-text-input wire:model.live.debounce.250ms="fcm_project_id" id="fcmProjectId" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="e.g. turf-booking-prod" />
                <x-input-error :messages="$errors->get('fcm_project_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="fcmServiceAccount" :value="__('Firebase Service Account Private Key JSON')" />
                <textarea wire:model.live.debounce.250ms="fcm_service_account_json" id="fcmServiceAccount" rows="8" class="mt-1.5 block w-full rounded-2xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm font-mono text-xs p-3 leading-relaxed" placeholder='{ "type": "service_account", "project_id": "...", "private_key": "...", ... }'></textarea>
                <x-input-error :messages="$errors->get('fcm_service_account_json')" class="mt-2" />
                <p class="text-[11px] text-gray-400 mt-1.5">{{ __('Paste the entire content of the downloaded service account JSON key file from Firebase Console (Project Settings > Service Accounts > Generate new private key).') }}</p>
            </div>
        </div>
    </form>
</div>
