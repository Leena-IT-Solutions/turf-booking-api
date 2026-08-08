<x-marketing-layout>
    <x-slot name="title">
        Contact - Reach Our Support & Arena Sales Experts
    </x-slot>

    <section class="py-20 lg:py-32 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-16 items-start">
            
            <!-- Contact Details Panel (Left Column) -->
            <div class="lg:col-span-5 space-y-10 text-left">
                <div class="space-y-4">
                    <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">GET IN TOUCH</span>
                    <h1 class="text-4xl font-black text-slate-900 leading-tight">
                        We're Here to Help <br>
                        <span class="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Your Business Grow</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-550 leading-relaxed">
                        Have technical questions about integrations? Want a customized quote for large franchises? Reach our venue success advisors directly.
                    </p>
                </div>

                <!-- Info Blocks -->
                <div class="space-y-6 text-xs sm:text-sm">
                    <!-- block 1: Email -->
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                            ✉
                        </div>
                        <div>
                            <span class="block font-bold text-slate-900">Email Address</span>
                            <a href="mailto:support@turfbooking.com" class="text-slate-500 hover:text-emerald-500 transition">support@turfbooking.com</a>
                        </div>
                    </div>

                    <!-- block 2: Phone -->
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                            ☎
                        </div>
                        <div>
                            <span class="block font-bold text-slate-900">Phone Helpline</span>
                            <span class="text-slate-500">+1 (800) 555-TURF</span>
                        </div>
                    </div>

                    <!-- block 3: Address -->
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                            📍
                        </div>
                        <div>
                            <span class="block font-bold text-slate-900">Headquarters Office</span>
                            <span class="text-slate-500">100 Sports Arena Way, Suite B, San Francisco, CA</span>
                        </div>
                    </div>
                </div>

                <!-- Sleek CSS Map Mockup -->
                <div class="relative bg-slate-100 border border-slate-200 rounded-2xl h-44 overflow-hidden shadow-inner flex items-center justify-center transition duration-300">
                    <!-- map grid lines -->
                    <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:14px_24px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)]"></div>
                    
                    <!-- Pulsing Green Dot -->
                    <div class="relative z-10 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </div>

                    <!-- location card float -->
                    <div class="absolute bottom-4 left-4 bg-white/90 border border-slate-200 px-3 py-1.5 rounded-lg shadow-md text-[9px] font-bold z-10">
                        <span class="block text-slate-950">TurfBooking HQ</span>
                        <span class="text-slate-450 font-medium">San Francisco, CA</span>
                    </div>
                </div>
            </div>

            <!-- Interactive Form (Right Column) -->
            <div class="lg:col-span-7 bg-white border border-slate-200 rounded-3xl p-8 shadow-md relative overflow-hidden">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-4">INQUIRY FORM</span>
                
                <!-- Alpine Form logic -->
                <form 
                    x-data="{ 
                        name: '', 
                        email: '', 
                        subject: '', 
                        message: '', 
                        loading: false, 
                        success: false,
                        submitForm() {
                            this.loading = true;
                            setTimeout(() => {
                                this.loading = false;
                                this.success = true;
                                this.name = '';
                                this.email = '';
                                this.subject = '';
                                this.message = '';
                            }, 1200);
                        }
                    }"
                    @submit.prevent="submitForm"
                    class="space-y-5 text-xs text-left"
                >
                    <!-- Success banner -->
                    <div x-show="success" x-transition class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-start gap-3">
                        <span class="text-emerald-500 text-sm">✓</span>
                        <div class="space-y-1">
                            <span class="block font-bold text-emerald-600">Message Transmitted Successfully!</span>
                            <p class="text-[10px] text-slate-500 leading-relaxed">Thank you. A venue success coordinator will review your ticket and reply within 12 business hours.</p>
                        </div>
                    </div>

                    <!-- Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label for="form_name" class="font-bold text-slate-700">Your Full Name</label>
                            <input 
                                id="form_name"
                                type="text" 
                                required 
                                x-model="name"
                                placeholder="E.g. David Beckham" 
                                class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                            >
                        </div>
                        <div class="space-y-1.5">
                            <label for="form_email" class="font-bold text-slate-700">Business Email</label>
                            <input 
                                id="form_email"
                                type="email" 
                                required 
                                x-model="email"
                                placeholder="E.g. david@arenagroup.com" 
                                class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                            >
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="form_subject" class="font-bold text-slate-700">Subject Matter</label>
                        <input 
                            id="form_subject"
                            type="text" 
                            required 
                            x-model="subject"
                            placeholder="E.g. Dynamic pricing settings help, or Multi-court setup" 
                            class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label for="form_message" class="font-bold text-slate-700">Message details</label>
                        <textarea 
                            id="form_message"
                            required 
                            rows="5"
                            x-model="message"
                            placeholder="Outline your venue constraints, court dimensions, or custom requirements here..." 
                            class="w-full text-xs px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800"
                        ></textarea>
                    </div>

                    <!-- Submit trigger -->
                    <button 
                        type="submit" 
                        :disabled="loading"
                        class="w-full inline-flex items-center justify-center py-4 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-emerald-500/10"
                    >
                        <span x-show="!loading">Send Message Inquiries</span>
                        <span x-show="loading" style="display: none;" class="flex items-center gap-2">
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
    </section>
</x-marketing-layout>
