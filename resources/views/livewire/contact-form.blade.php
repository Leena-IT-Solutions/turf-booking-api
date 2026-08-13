<?php

use Livewire\Volt\Component;
use App\Models\ContactMessage;

new class extends Component
{
    public $name = '';
    public $email = '';
    public $user_type = 'owner';
    public $reason = '';
    public $contact_no = '';
    public $subject = '';
    public $message = '';
    
    public $success = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'user_type' => 'required|string|in:owner,player',
        'reason' => 'required|string',
        'contact_no' => ['required', 'string', 'regex:/^[6-9][0-9]{9}$/'],
        'subject' => 'required|string|max:255',
        'message' => 'required|string',
    ];

    public function updatedUserType($value)
    {
        $this->reason = '';
    }

    public function submitForm()
    {
        $this->validate();

        ContactMessage::create([
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'reason' => $this->reason,
            'contact_no' => $this->contact_no,
            'subject' => $this->subject,
            'message' => $this->message,
        ]);

        $this->reset(['name', 'email', 'user_type', 'reason', 'contact_no', 'subject', 'message']);
        $this->success = true;
    }
}; ?>

<div>
    <!-- Interactive Form -->
    <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-md relative overflow-hidden text-left">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-4">INQUIRY FORM</span>
        
        <form wire:submit.prevent="submitForm" class="space-y-5 text-xs">
            <!-- Success banner -->
            @if ($success)
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-start gap-3">
                    <span class="text-emerald-500 text-sm">✓</span>
                    <div class="space-y-1">
                        <span class="block font-bold text-emerald-600">Message Transmitted Successfully!</span>
                        <p class="text-[10px] text-slate-500 leading-relaxed">Thank you. A venue success coordinator will review your ticket and reply within 12 business hours.</p>
                    </div>
                </div>
            @endif

            <!-- Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="form_name" class="font-bold text-slate-700">Your Full Name</label>
                    <input 
                        id="form_name"
                        type="text" 
                        required 
                        wire:model="name"
                        placeholder="E.g. David Beckham" 
                        class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                    >
                    @error('name') <span class="text-red-550 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-1.5">
                    <label for="form_email" class="font-bold text-slate-700">Business Email</label>
                    <input 
                        id="form_email"
                        type="email" 
                        required 
                        wire:model="email"
                        placeholder="E.g. david@arenagroup.com" 
                        class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                    >
                    @error('email') <span class="text-red-555 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Role Dropdown Selection -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="form_user_type" class="font-bold text-slate-700">I am a...</label>
                    <select 
                        id="form_user_type"
                        wire:model.live="user_type" 
                        required 
                        class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800 cursor-pointer"
                    >
                        <option value="owner">I'm a Turf Owner</option>
                        <option value="player">I'm a Player</option>
                    </select>
                    @error('user_type') <span class="text-red-555 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <!-- Dynamic Reason Dropdown -->
                <div class="space-y-1.5">
                    <label for="form_reason" class="font-bold text-slate-700">Inquiry Reason</label>
                    <select 
                        id="form_reason"
                        wire:model="reason" 
                        required 
                        class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800 cursor-pointer"
                    >
                        <option value="">-- Select Reason --</option>
                        @if ($user_type === 'owner')
                            <option value="Sales">Sales</option>
                            <option value="Subscription">Subscription</option>
                            <option value="Listing">Listing</option>
                            <option value="Technical support">Technical support</option>
                        @elseif ($user_type === 'player')
                            <option value="Booking support">Booking support</option>
                            <option value="Payment issues">Payment issues</option>
                            <option value="App support">App support</option>
                        @endif
                    </select>
                    @error('reason') <span class="text-red-555 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Contact No Field -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="form_contact_no" class="font-bold text-slate-700">Contact Number</label>
                    <input 
                        id="form_contact_no"
                        type="text" 
                        maxlength="10"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        required 
                        wire:model="contact_no"
                        placeholder="E.g. 9876543210" 
                        class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                    >
                    @error('contact_no') <span class="text-red-555 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="form_subject" class="font-bold text-slate-700">Subject Matter</label>
                    <input 
                        id="form_subject"
                        type="text" 
                        required 
                        wire:model="subject"
                        placeholder="E.g. Dynamic pricing settings help, or Multi-court setup" 
                        class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                    >
                    @error('subject') <span class="text-red-555 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="form_message" class="font-bold text-slate-700">Message details</label>
                <textarea 
                    id="form_message"
                    required 
                    rows="5"
                    wire:model="message"
                    placeholder="Outline your venue constraints, court dimensions, or custom requirements here..." 
                    class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                ></textarea>
                @error('message') <span class="text-red-555 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Submit trigger -->
            <button 
                type="submit" 
                wire:loading.attr="disabled"
                class="w-full inline-flex items-center justify-center py-4 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-emerald-500/10 cursor-pointer"
            >
                <span wire:loading.remove>Send Message Inquiries</span>
                <span wire:loading class="flex items-center gap-2">
                    <!-- spinner -->
                    <svg class="animate-spin h-5 w-5 text-slate-950" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Transmitting...
                </span>
            </button>
        </form>
    </div>
</div>
