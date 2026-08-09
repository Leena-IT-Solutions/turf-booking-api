<?php

use App\Models\ContactMessage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filters
    public $search = '';
    public $status = 'all'; // all, read, unread

    // View State
    public $selectedMessageId = null;
    public $showDetailModal = false;

    // Delete State
    public $deletingId = null;
    public $showDeleteConfirm = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function openDetailModal($id)
    {
        $this->selectedMessageId = $id;
        
        $message = ContactMessage::findOrFail($id);
        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }
        
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedMessageId = null;
    }

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->showDeleteConfirm = false;
    }

    public function performDelete()
    {
        if ($this->deletingId) {
            ContactMessage::destroy($this->deletingId);
            session()->flash('status', __('Message deleted successfully.'));
        }
        $this->cancelDelete();
    }

    public function markAsUnread($id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['is_read' => false]);
        session()->flash('status', __('Message marked as unread.'));
    }

    public function with()
    {
        $query = ContactMessage::query();

        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('subject', 'like', '%' . $this->search . '%')
                  ->orWhere('message', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->status === 'read') {
            $query->where('is_read', true);
        } elseif ($this->status === 'unread') {
            $query->where('is_read', false);
        }

        return [
            'messages' => $query->latest()->paginate(10),
            'selectedMessage' => $this->selectedMessageId ? ContactMessage::find($this->selectedMessageId) : null,
        ];
    }
}; ?>

<div x-data="{ detailModal: @entangle('showDetailModal'), deleteConfirm: @entangle('showDeleteConfirm') }" class="p-6 space-y-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('Contact Messages') }}</h1>
            <p class="text-xs text-gray-500 mt-1">{{ __('Manage and review inquiries submitted by users through the contact form.') }}</p>
        </div>
    </div>

    <!-- Sessions status -->
    @if (session('status'))
        <div class="p-4 bg-emerald-50 border border-emerald-250 text-emerald-800 text-xs font-bold rounded-2xl flex items-center justify-between">
            <span>{{ session('status') }}</span>
            <button type="button" class="text-emerald-500 hover:text-emerald-700" onclick="this.parentElement.remove()">✕</button>
        </div>
    @endif

    <!-- Toolbar Filters -->
    <div class="flex flex-col sm:flex-row gap-4 justify-between items-stretch sm:items-center bg-white p-4 rounded-2xl border border-gray-150 shadow-xs">
        <div class="flex-grow max-w-md relative">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Search by sender, email, subject, content...') }}" 
                class="w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-gray-50 border border-gray-250 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            >
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                🔍
            </div>
        </div>

        <div class="flex items-center gap-2">
            <label for="filter_status" class="text-xs text-gray-550 font-bold uppercase tracking-wider">{{ __('Filter Status:') }}</label>
            <select 
                id="filter_status"
                wire:model.live="status" 
                class="px-4 py-2.5 text-xs rounded-xl bg-gray-50 border border-gray-250 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 cursor-pointer"
            >
                <option value="all">{{ __('All Messages') }}</option>
                <option value="unread">{{ __('Unread Only') }}</option>
                <option value="read">{{ __('Read Only') }}</option>
            </select>
        </div>
    </div>

    <!-- Messages List -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-150 text-gray-500 font-bold uppercase tracking-wider text-[10px]">
                        <th class="px-6 py-4 w-12">{{ __('Status') }}</th>
                        <th class="px-6 py-4">{{ __('Sender') }}</th>
                        <th class="px-6 py-4">{{ __('Subject') }}</th>
                        <th class="px-6 py-4">{{ __('Date') }}</th>
                        <th class="px-6 py-4 text-right w-36">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-150">
                    @forelse ($messages as $message)
                        <tr class="hover:bg-gray-50/50 transition duration-150 {{ !$message->is_read ? 'bg-indigo-50/10 font-medium' : '' }}">
                            <td class="px-6 py-4">
                                @if (!$message->is_read)
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-indigo-600 ring-4 ring-indigo-550/20" title="{{ __('Unread') }}"></span>
                                @else
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-gray-300" title="{{ __('Read') }}"></span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900">{{ $message->name }}</div>
                                <div class="text-[10px] text-gray-500 mt-0.5">{{ $message->email }}</div>
                                @if($message->contact_no)
                                    <div class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $message->contact_no }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    @if ($message->user_type === 'owner')
                                        <span class="inline-flex px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-100">Owner</span>
                                    @elseif ($message->user_type === 'player')
                                        <span class="inline-flex px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-teal-50 text-teal-700 border border-teal-100">Player</span>
                                    @endif
                                    @if($message->reason)
                                        <span class="inline-flex px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-gray-100 text-gray-600 border border-gray-200">{{ $message->reason }}</span>
                                    @endif
                                </div>
                                <div class="text-gray-900 font-semibold truncate max-w-xs">{{ $message->subject }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5 truncate max-w-sm">{{ Str::limit($message->message, 60) }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-500 font-mono text-[10px]">
                                {{ $message->created_at->format('M d, Y h:i A') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button 
                                        type="button" 
                                        wire:click="openDetailModal({{ $message->id }})" 
                                        class="p-2 text-indigo-650 hover:bg-indigo-50 rounded-lg cursor-pointer"
                                        title="{{ __('View Details') }}"
                                    >
                                        📄
                                    </button>
                                    @if ($message->is_read)
                                        <button 
                                            type="button" 
                                            wire:click="markAsUnread({{ $message->id }})" 
                                            class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg cursor-pointer"
                                            title="{{ __('Mark Unread') }}"
                                        >
                                            ✉
                                        </button>
                                    @endif
                                    <button 
                                        type="button" 
                                        wire:click="confirmDelete({{ $message->id }})" 
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
                                        title="{{ __('Delete') }}"
                                    >
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                <span class="block text-2xl mb-2">📥</span>
                                {{ __('No contact messages found matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Links -->
        @if ($messages->hasPages())
            <div class="px-6 py-4 border-t border-gray-150">
                {{ $messages->links() }}
            </div>
        @endif
    </div>

    <!-- Detail View Modal -->
    <div x-show="detailModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="detailModal" @click="detailModal = false" class="fixed inset-0 transition-opacity bg-gray-950/40 backdrop-blur-sm"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div x-show="detailModal" class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-gray-100">
                <div class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h3 class="text-base font-bold text-gray-900">
                            {{ __('Message Details') }}
                        </h3>
                        <button type="button" @click="detailModal = false" class="text-gray-400 hover:text-gray-500 cursor-pointer">
                            ✕
                        </button>
                    </div>

                    @if ($selectedMessage)
                        <div class="space-y-6 mt-6 text-xs">
                            <!-- Sender & Info -->
                            <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-2xl border border-gray-150">
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">{{ __('Sender') }}</span>
                                    <span class="font-bold text-gray-900 block">{{ $selectedMessage->name }}</span>
                                    <a href="mailto:{{ $selectedMessage->email }}" class="text-indigo-650 hover:underline mt-0.5 inline-block">{{ $selectedMessage->email }}</a>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">{{ __('Submitted At') }}</span>
                                    <span class="text-gray-900 block font-semibold font-mono">{{ $selectedMessage->created_at->format('M d, Y h:i A') }}</span>
                                </div>
                            </div>

                            <!-- User Type, Reason, and Contact Number Grid -->
                            <div class="grid grid-cols-3 gap-4 bg-gray-50 p-4 rounded-2xl border border-gray-150">
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">{{ __('User Type') }}</span>
                                    <span class="font-bold text-gray-900 block">
                                        @if ($selectedMessage->user_type === 'owner')
                                            {{ __('Turf Owner') }}
                                        @elseif ($selectedMessage->user_type === 'player')
                                            {{ __('Player') }}
                                        @else
                                            {{ __('N/A') }}
                                        @endif
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">{{ __('Inquiry Reason') }}</span>
                                    <span class="font-bold text-gray-900 block">{{ $selectedMessage->reason ?? __('N/A') }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">{{ __('Contact Number') }}</span>
                                    @if ($selectedMessage->contact_no)
                                        <a href="tel:{{ $selectedMessage->contact_no }}" class="font-bold text-indigo-650 hover:underline block font-mono">{{ $selectedMessage->contact_no }}</a>
                                    @else
                                        <span class="text-gray-500 block">{{ __('N/A') }}</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Subject -->
                            <div class="space-y-1">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ __('Subject Matter') }}</span>
                                <div class="text-sm font-bold text-gray-900">{{ $selectedMessage->subject }}</div>
                            </div>

                            <!-- Message Content -->
                            <div class="space-y-2">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ __('Inquiry Description') }}</span>
                                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-150 whitespace-pre-wrap leading-relaxed text-gray-800 font-medium">
                                    {{ $selectedMessage->message }}
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="detailModal = false" 
                            class="px-4 py-2 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl transition cursor-pointer"
                        >
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="deleteConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="deleteConfirm" @click="deleteConfirm = false" class="fixed inset-0 transition-opacity bg-gray-950/40 backdrop-blur-sm"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div x-show="deleteConfirm" class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full border border-gray-100">
                <div class="p-6">
                    <div class="text-center space-y-4">
                        <span class="text-4xl">⚠️</span>
                        <h3 class="text-base font-bold text-gray-900">{{ __('Confirm Delete') }}</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-semibold">
                            {{ __('Are you sure you want to permanently delete this contact inquiry message? This action is irreversible.') }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-center gap-3">
                        <button 
                            type="button" 
                            wire:click="cancelDelete" 
                            class="px-4 py-2.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl transition cursor-pointer"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button 
                            type="button" 
                            wire:click="performDelete" 
                            class="px-4 py-2.5 text-xs font-bold bg-red-600 hover:bg-red-500 text-white rounded-xl transition cursor-pointer"
                        >
                            {{ __('Delete Message') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
