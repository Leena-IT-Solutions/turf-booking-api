<?php

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $title = '';
    public $body = '';
    public $audience = 'all';
    public $target_user_id = null;
    public $search = '';
    public $selected_user = null;

    public function updatedAudience()
    {
        if ($this->audience !== 'specific_user') {
            $this->target_user_id = null;
            $this->selected_user = null;
            $this->search = '';
        }
    }

    public function selectUser($userId)
    {
        $user = User::find($userId);
        if ($user) {
            $this->target_user_id = $user->id;
            $this->selected_user = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
            ];
            $this->search = '';
        }
    }

    public function clearSelectedUser()
    {
        $this->target_user_id = null;
        $this->selected_user = null;
        $this->search = '';
    }

    public function sendNotification()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
            'audience' => 'required|in:all,customers,turf_admins,specific_user',
            'target_user_id' => 'required_if:audience,specific_user|nullable|exists:users,id',
        ], [
            'target_user_id.required_if' => 'Please select a specific user to send the notification.',
        ]);

        $recipientCount = NotificationService::sendCustomNotification(
            $this->title,
            $this->body,
            $this->audience,
            $this->target_user_id,
            auth()->user()
        );

        if ($recipientCount > 0) {
            session()->flash('status', __("Push notification successfully dispatched to :count recipient(s).", ['count' => $recipientCount]));
            $this->title = '';
            $this->body = '';
            $this->audience = 'all';
            $this->target_user_id = null;
            $this->selected_user = null;
            $this->search = '';
        } else {
            session()->flash('warning', __("0 recipients — no matching users have a registered device."));
        }
    }

    public function with()
    {
        $searchResults = [];
        if ($this->audience === 'specific_user' && strlen(trim($this->search)) >= 2) {
            $query = trim($this->search);
            $searchResults = User::where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('mobile', 'LIKE', "%{$query}%");
            })
            ->limit(8)
            ->get(['id', 'name', 'email', 'mobile']);
        }

        $recentLogs = NotificationLog::with(['sentByUser', 'targetUser'])
            ->latest()
            ->limit(20)
            ->get();

        return [
            'searchResults' => $searchResults,
            'recentLogs' => $recentLogs,
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Header Card -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Broadcast Push Notifications') }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Compose and send instant push notifications directly to players and turf admins.') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('saas.settings.notifications') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-xl font-bold text-xs uppercase tracking-wider border border-gray-200 transition">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>{{ __('Notification Settings') }}</span>
                </a>
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

        @if (session()->has('warning'))
            <div class="bg-amber-50 border border-amber-100 text-amber-800 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        <!-- Composer Card -->
        <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
            <div class="pb-4 border-b border-gray-100 flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Compose Notification') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Select target audience and enter notification content.') }}</p>
                </div>
            </div>

            <form wire:submit="sendNotification" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Target Audience -->
                    <div>
                        <x-input-label for="audienceSelect" :value="__('Target Audience')" />
                        <select wire:model.live="audience" id="audienceSelect" class="mt-1.5 block w-full rounded-2xl border-gray-200 bg-gray-50/60 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-800 transition">
                            <option value="all">{{ __('All Users (Customers & Admins)') }}</option>
                            <option value="customers">{{ __('Customers Only') }}</option>
                            <option value="turf_admins">{{ __('Turf Admins & Managers Only') }}</option>
                            <option value="specific_user">{{ __('Specific User') }}</option>
                        </select>
                        <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                    </div>

                    <!-- Notification Title -->
                    <div>
                        <x-input-label for="notificationTitle" :value="__('Notification Title')" />
                        <x-text-input wire:model="title" id="notificationTitle" type="text" class="mt-1.5 block w-full text-xs font-semibold" placeholder="e.g. Weekend Special Tournament!" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>
                </div>

                <!-- Specific User Selection Block (Conditional) -->
                @if ($audience === 'specific_user')
                    <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-900">{{ __('Target User Selection') }}</span>
                            @if ($selected_user)
                                <button type="button" wire:click="clearSelectedUser" class="text-xs text-rose-600 font-bold hover:underline cursor-pointer">
                                    {{ __('Change User') }}
                                </button>
                            @endif
                        </div>

                        @if ($selected_user)
                            <div class="flex items-center justify-between bg-white p-3 rounded-xl border border-indigo-200">
                                <div>
                                    <div class="text-xs font-bold text-gray-900">{{ $selected_user['name'] }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $selected_user['email'] ?? 'No email' }} &bull; {{ $selected_user['mobile'] ?? 'No mobile' }}</div>
                                </div>
                                <span class="px-2.5 py-1 bg-indigo-100 text-indigo-800 text-[10px] font-black rounded-lg uppercase">
                                    {{ __('Selected') }}
                                </span>
                            </div>
                        @else
                            <div class="relative">
                                <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Search user by name, email, or mobile...') }}" 
                                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-xs bg-white text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                                <div class="absolute left-3.5 top-3 text-gray-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>

                            @if (!empty($searchResults) && count($searchResults) > 0)
                                <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 shadow-sm max-h-48 overflow-y-auto">
                                    @foreach ($searchResults as $user)
                                        <div wire:click="selectUser({{ $user->id }})" class="p-2.5 hover:bg-indigo-50 cursor-pointer flex items-center justify-between transition">
                                            <div>
                                                <span class="text-xs font-bold text-gray-800 block">{{ $user->name }}</span>
                                                <span class="text-[10px] text-gray-500">{{ $user->email }} &bull; {{ $user->mobile }}</span>
                                            </div>
                                            <span class="text-[11px] text-indigo-600 font-bold hover:underline">{{ __('Select') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif (strlen(trim($search)) >= 2)
                                <p class="text-xs text-gray-500 italic">{{ __('No matching users found.') }}</p>
                            @endif
                        @endif
                        <x-input-error :messages="$errors->get('target_user_id')" class="mt-1" />
                    </div>
                @endif

                <!-- Notification Body -->
                <div>
                    <x-input-label for="notificationBody" :value="__('Message Body')" />
                    <textarea wire:model="body" id="notificationBody" rows="4" class="mt-1.5 block w-full rounded-2xl border-gray-200 bg-gray-50/60 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-medium text-gray-800 p-3 leading-relaxed" placeholder="{{ __('Type your announcement or alert message here...') }}"></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition duration-150 cursor-pointer disabled:opacity-50">
                        <svg wire:loading.remove wire:target="sendNotification" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        <svg wire:loading wire:target="sendNotification" class="animate-spin w-4 h-4 text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ __('Send Notification') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Notification Broadcast Logs Card -->
        <div class="bg-white rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-600 shadow-sm shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Recent Broadcast Logs (Last 20)') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Audit trail of manual custom notifications sent from SaaS admin.') }}</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/80 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="p-3">{{ __('Sent At') }}</th>
                            <th class="p-3">{{ __('Sent By') }}</th>
                            <th class="p-3">{{ __('Title & Message') }}</th>
                            <th class="p-3">{{ __('Audience') }}</th>
                            <th class="p-3 text-right">{{ __('Recipients') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse ($recentLogs as $log)
                            <tr class="hover:bg-gray-50/50">
                                <td class="p-3 whitespace-nowrap">
                                    <span class="font-bold block text-gray-900">{{ $log->created_at ? $log->created_at->format('d M Y') : 'N/A' }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $log->created_at ? $log->created_at->format('h:i A') : '' }}</span>
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <span class="font-bold block text-gray-900">{{ $log->sentByUser->name ?? 'User #' . $log->sent_by_user_id }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $log->sentByUser->email ?? '' }}</span>
                                </td>
                                <td class="p-3 max-w-sm">
                                    <span class="font-bold text-gray-900 block truncate">{{ $log->title }}</span>
                                    <span class="text-[11px] text-gray-500 line-clamp-2 mt-0.5 leading-relaxed">{{ $log->body }}</span>
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    @if ($log->audience_type === 'all')
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            {{ __('All Users') }}
                                        </span>
                                    @elseif ($log->audience_type === 'customers')
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            {{ __('Customers') }}
                                        </span>
                                    @elseif ($log->audience_type === 'turf_admins')
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-purple-50 text-purple-700 border border-purple-100">
                                            {{ __('Turf Admins') }}
                                        </span>
                                    @elseif ($log->audience_type === 'specific_user')
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-100">
                                            {{ __('User: ') }} {{ $log->targetUser->name ?? '#' . $log->target_user_id }}
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-gray-100 text-gray-700">
                                            {{ $log->audience_type }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <span class="font-bold font-mono text-sm {{ $log->recipient_count > 0 ? 'text-indigo-600' : 'text-gray-400' }}">
                                        {{ $log->recipient_count }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-6 text-center text-xs text-gray-400">
                                    {{ __('No broadcast push notifications logged yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
