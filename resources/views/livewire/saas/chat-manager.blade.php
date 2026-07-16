<?php

use App\Models\User;
use App\Models\SupportMessage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $activeCustomerId = null;
    public $replyMessage = '';
    public $search = '';

    public function selectCustomer($id)
    {
        $this->activeCustomerId = $id;

        // Mark messages as read by admin
        SupportMessage::where('user_id', $id)
            ->where('sender_id', $id)
            ->where('is_read_by_admin', false)
            ->update(['is_read_by_admin' => true]);
            
        $this->dispatch('scroll-to-bottom');
    }

    public function sendReply()
    {
        if (empty(trim($this->replyMessage)) || !$this->activeCustomerId) {
            return;
        }

        SupportMessage::create([
            'user_id' => $this->activeCustomerId,
            'sender_id' => auth()->id(),
            'message' => trim($this->replyMessage),
            'is_read_by_user' => false,
            'is_read_by_admin' => true,
        ]);

        $this->replyMessage = '';
        $this->dispatch('scroll-to-bottom');
    }

    public function with()
    {
        // 1. Contacts List
        $search = trim($this->search);
        
        // Find customers who have either initiated a support chat or match the search query
        $contactsQuery = User::whereHas('roles', function ($q) {
            $q->where('name', 'customer');
        });
        
        if (!empty($search)) {
            $contactsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }
        
        $contacts = $contactsQuery->get()
            ->map(function ($user) {
                $latestMsg = SupportMessage::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                    
                $user->latest_message = $latestMsg ? $latestMsg->message : '';
                $user->latest_message_time = $latestMsg ? $latestMsg->created_at : null;
                
                $user->unread_count = SupportMessage::where('user_id', $user->id)
                    ->where('sender_id', $user->id)
                    ->where('is_read_by_admin', false)
                    ->count();
                    
                return $user;
            })
            // Filter out users who have never sent a message if we are not actively searching
            ->filter(function ($user) use ($search) {
                return !empty($search) || !empty($user->latest_message);
            })
            ->sortByDesc('latest_message_time')
            ->values();

        // 2. Active Chat Messages
        $messages = [];
        $activeCustomer = null;
        
        if ($this->activeCustomerId) {
            $activeCustomer = User::find($this->activeCustomerId);
            if ($activeCustomer) {
                $messages = SupportMessage::where('user_id', $this->activeCustomerId)
                    ->orderBy('created_at', 'asc')
                    ->get();
                    
                // Auto-read messages when refreshing/polling
                SupportMessage::where('user_id', $this->activeCustomerId)
                    ->where('sender_id', $this->activeCustomerId)
                    ->where('is_read_by_admin', false)
                    ->update(['is_read_by_admin' => true]);
            }
        }

        return [
            'contacts' => $contacts,
            'messages' => $messages,
            'activeCustomer' => $activeCustomer,
        ];
    }
}; ?>

<div class="flex-1 flex min-h-0 bg-white dark:bg-gray-800 overflow-hidden" wire:poll.5s>
<style>
    /* Constrain the content area wrapper to viewport height */
    div.lg\:ps-64 {
        height: 100vh !important;
        max-height: 100vh !important;
        overflow: hidden !important;
    }
    
    /* Make main occupy the exact remaining height without padding */
    main {
        padding: 0 !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        flex-grow: 1 !important;
        min-height: 0 !important;
    }
</style>
            
            <!-- Left Sidebar (Contacts List) -->
            <div class="w-80 border-r border-gray-100 dark:border-gray-700/50 flex flex-col h-full min-h-0 bg-gray-50/30 dark:bg-gray-900/10">
                <!-- Search Bar -->
                <div class="p-4 border-b border-gray-100 dark:border-gray-700/50">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3">{{ __('Conversations') }}</h3>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            wire:model.live="search"
                            type="text" 
                            placeholder="Search customer..." 
                            class="w-full text-xs rounded-2xl bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 pl-10 pr-4 py-2.5 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Contacts Scroll Area -->
                <div class="flex-1 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-800/40">
                    @forelse ($contacts as $contact)
                        <button 
                            wire:key="contact-{{ $contact->id }}"
                            wire:click="selectCustomer({{ $contact->id }})"
                            class="w-full p-4 flex items-start gap-3 transition text-left hover:bg-gray-50 dark:hover:bg-gray-800/30 {{ $activeCustomerId === $contact->id ? 'bg-indigo-50/50 dark:bg-indigo-950/10' : '' }}"
                        >
                            <!-- Avatar -->
                            <div class="shrink-0 h-10 w-10 rounded-full bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center border border-indigo-100/50 dark:border-indigo-950/20 text-indigo-600 dark:text-indigo-400 font-bold text-sm">
                                {{ strtoupper(substr($contact->name, 0, 2)) }}
                            </div>

                            <!-- Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline">
                                    <span class="font-bold text-xs text-gray-900 dark:text-gray-100 truncate">{{ $contact->name }}</span>
                                    @if ($contact->latest_message_time)
                                        <span class="text-[9px] text-gray-400 dark:text-gray-500 font-medium">
                                            {{ $contact->latest_message_time->diffForHumans(null, true) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-1">
                                    {{ $contact->latest_message }}
                                </p>
                            </div>

                            <!-- Unread Badge -->
                            @if ($contact->unread_count > 0)
                                <span class="shrink-0 h-5 w-5 bg-emerald-500 text-white rounded-full flex items-center justify-center text-[9px] font-black shadow-sm shadow-emerald-500/25">
                                    {{ $contact->unread_count }}
                                </span>
                            @endif
                        </button>
                    @empty
                        <div class="py-12 text-center text-xs text-gray-400 dark:text-gray-500">
                            <span class="text-3xl block mb-2">💬</span>
                            {{ __('No conversations found') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right Chat Window -->
            <div class="flex-1 flex flex-col h-full min-h-0 bg-white dark:bg-gray-800">
                @if ($activeCustomer)
                    <!-- Chat Header -->
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700/50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center border border-indigo-100/50 dark:border-indigo-950/20 text-indigo-600 dark:text-indigo-400 font-bold text-sm">
                                {{ strtoupper(substr($activeCustomer->name, 0, 2)) }}
                            </div>
                            <div>
                                <h4 class="font-bold text-xs text-gray-900 dark:text-gray-100">{{ $activeCustomer->name }}</h4>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ $activeCustomer->email }} • {{ $activeCustomer->mobile }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Messages Stream -->
                    <div 
                        id="chat-messages-container"
                        x-data
                        x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight; })"
                        @scroll-to-bottom.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight; })"
                        class="flex-1 min-h-0 p-6 overflow-y-auto space-y-4 bg-gray-50/10 dark:bg-gray-900/5"
                    >
                        @foreach ($messages as $msg)
                            <div wire:key="msg-{{ $msg->id }}" class="flex w-full">
                                @if ($msg->sender_id === $activeCustomer->id)
                                    <!-- Left Bubble (Customer) -->
                                    <div class="flex items-end gap-2 max-w-[80%]">
                                        <div class="bg-gray-100 dark:bg-gray-700/50 text-gray-900 dark:text-gray-100 rounded-3xl rounded-bl-none px-4 py-2.5 shadow-sm text-xs leading-relaxed">
                                            {{ $msg->message }}
                                            <span class="block text-[8px] text-gray-400 dark:text-gray-500 text-right mt-1 font-mono">
                                                {{ $msg->created_at->format('h:i A') }}
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <!-- Right Bubble (Staff Reply) -->
                                    <div class="flex items-end gap-2 max-w-[80%] ml-auto justify-end">
                                        <div class="bg-indigo-600 text-white rounded-3xl rounded-br-none px-4 py-2.5 shadow-sm text-xs leading-relaxed">
                                            {{ $msg->message }}
                                            <span class="block text-[8px] text-indigo-200 text-right mt-1 font-mono">
                                                {{ $msg->created_at->format('h:i A') }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Chat Bottom Input -->
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-800">
                        <form wire:submit.prevent="sendReply" class="flex gap-3">
                            <input 
                                id="reply-input-field"
                                wire:model="replyMessage"
                                x-data
                                x-init="$nextTick(() => { $el.focus(); })"
                                type="text" 
                                placeholder="Type a message..." 
                                class="flex-1 text-xs rounded-2xl bg-gray-50 dark:bg-gray-900/30 border-gray-100 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3"
                            />
                            <button 
                                type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2 rounded-2xl transition text-xs shrink-0 flex items-center justify-center shadow-sm shadow-indigo-600/25"
                            >
                                {{ __('Send') }}
                            </button>
                        </form>
                    </div>
                @else
                    <!-- Empty State View -->
                    <div class="flex-1 flex flex-col items-center justify-center text-center p-12">
                        <span class="text-5xl mb-4">💬</span>
                        <h4 class="font-extrabold text-sm text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Support Workspace') }}</h4>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm leading-relaxed">
                            {{ __('Select a conversation from the sidebar list to view the support details and reply in real-time.') }}
                        </p>
                    </div>
                @endif

            </div>
</div>


