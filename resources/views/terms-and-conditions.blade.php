<x-marketing-layout>
    <x-slot name="title">
        Terms & Conditions - TurfBooking
    </x-slot>
    <x-slot name="description">
        Read the terms and conditions for using the TurfBooking platform, reservation services, user conduct, and payment policies.
    </x-slot>

    <!-- Hero Header -->
    <section class="pt-24 pb-16 text-center relative overflow-hidden bg-slate-50 border-b border-slate-200/50">
        <!-- Ambient decorative glows -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-indigo-500/5 blur-3xl"></div>
            <div class="absolute bottom-0 -right-40 w-96 h-96 rounded-full bg-emerald-500/5 blur-3xl"></div>
        </div>

        <div class="max-w-4xl mx-auto px-6 space-y-4 relative z-10">
            <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest bg-emerald-50 border border-emerald-100 px-3.5 py-1.5 rounded-full inline-block">Platform Agreement</span>
            <h1 class="text-4xl sm:text-5xl font-black tracking-tight text-slate-900 leading-tight">
                Terms & Conditions
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 max-w-2xl mx-auto leading-relaxed font-semibold">
                Please read these terms and conditions carefully before using our platform, booking slots, or listing sports venues.
            </p>
        </div>
    </section>

    <!-- Content Sections -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-6 space-y-12">
            
            <!-- Quick Summary Alert -->
            <div class="bg-indigo-50/50 border border-indigo-150 rounded-3xl p-6 sm:p-8 space-y-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">📋</span>
                    <h2 class="text-lg font-black text-indigo-900 tracking-tight">Agreement Summary</h2>
                </div>
                <p class="text-xs text-indigo-700 leading-relaxed font-medium">
                    By accessing or using the TurfBooking platform, apps, and website, you agree to comply with and be bound by these Terms & Conditions. These terms govern the relationship between TurfBooking (the platform provider), players (users seeking to book venues), and turf owners (operators listing their venues).
                </p>
            </div>

            <!-- Detailed Breakdown Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Card 1: Platform Service -->
                <div class="bg-slate-50 border border-slate-150 rounded-2xl p-6 space-y-4 hover:border-emerald-200 hover:shadow-md transition duration-300">
                    <div class="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg shadow-sm">
                        🌐
                    </div>
                    <h3 class="text-base font-bold text-slate-900">1. Nature of the Platform</h3>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        TurfBooking acts strictly as a technology intermediary between sports players and venue operators. We provide scheduling, booking verification, and secure payment processing services. We do not own, manage, or operate any physical venues listed on the platform.
                    </p>
                </div>

                <!-- Card 2: Account Registration -->
                <div class="bg-slate-50 border border-slate-150 rounded-2xl p-6 space-y-4 hover:border-emerald-200 hover:shadow-md transition duration-300">
                    <div class="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg shadow-sm">
                        🔑
                    </div>
                    <h3 class="text-base font-bold text-slate-900">2. Account Responsibility</h3>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        To book slots or register a venue, you must create an account. You are solely responsible for maintaining the confidentiality of your credentials and for all activities that occur under your account. You agree to provide accurate and complete registration info.
                    </p>
                </div>

                <!-- Card 3: Payments & Bookings -->
                <div class="bg-slate-50 border border-slate-150 rounded-2xl p-6 space-y-4 hover:border-emerald-200 hover:shadow-md transition duration-300">
                    <div class="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg shadow-sm">
                        💰
                    </div>
                    <h3 class="text-base font-bold text-slate-900">3. Booking Confirmation</h3>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        A booking slot is officially confirmed only when the complete payment is successful. Rates, slot durations, availability, peak hours, and discounts are determined entirely by the venue operators and are subject to change.
                    </p>
                </div>

                <!-- Card 4: Cancellation Policy -->
                <div class="bg-slate-50 border border-slate-150 rounded-2xl p-6 space-y-4 hover:border-emerald-200 hover:shadow-md transition duration-300">
                    <div class="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg shadow-sm">
                        🔄
                    </div>
                    <h3 class="text-base font-bold text-slate-900">4. Refunds & Cancellation</h3>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        Cancellations and refunds are subject to the specific turf operator's own cancellation policy. Players are required to check the specific refund terms before paying. Please see our <a href="{{ route('refund-policy') }}" class="text-emerald-500 font-bold hover:underline">Refund Policy</a> page for detailed guidance.
                    </p>
                </div>

            </div>

            <!-- Conduct Guidelines -->
            <div class="bg-slate-50 border border-slate-200/50 rounded-3xl p-8 space-y-6">
                <h3 class="text-lg font-black text-slate-900 tracking-tight">5. Venue Rules & Conduct</h3>
                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                    By booking a venue through TurfBooking, you agree to comply with the rules set by the venue management, including but not limited to:
                </p>
                <div class="relative pl-8 space-y-4">
                    <div class="absolute left-[11px] top-2 bottom-2 w-[2px] bg-indigo-100"></div>

                    <!-- Point 1 -->
                    <div class="relative">
                        <div class="absolute left-[-27px] top-1.5 w-3 h-3 rounded-full bg-indigo-50 border-2 border-indigo-500"></div>
                        <h4 class="text-xs font-bold text-slate-800">Punctuality</h4>
                        <p class="text-[11px] text-slate-500 font-medium">You must arrive on time for your slot. Venues will not extend slots due to late arrival.</p>
                    </div>

                    <!-- Point 2 -->
                    <div class="relative">
                        <div class="absolute left-[-27px] top-1.5 w-3 h-3 rounded-full bg-indigo-50 border-2 border-indigo-500"></div>
                        <h4 class="text-xs font-bold text-slate-800">Gear & Safety</h4>
                        <p class="text-[11px] text-slate-500 font-medium">Wear proper footwear and sports gear as mandated by the venue (e.g. non-marking shoes for indoor turfs).</p>
                    </div>

                    <!-- Point 3 -->
                    <div class="relative">
                        <div class="absolute left-[-27px] top-1.5 w-3 h-3 rounded-full bg-indigo-50 border-2 border-indigo-500"></div>
                        <h4 class="text-xs font-bold text-slate-800">Property Integrity</h4>
                        <p class="text-[11px] text-slate-500 font-medium">You are liable for any damages to the turf property, nets, or gear caused by negligence or violation of ground rules.</p>
                    </div>
                </div>
            </div>

            <!-- Disclaimer and Limitation of Liability -->
            <div class="space-y-4">
                <h3 class="text-lg font-black text-slate-900 tracking-tight">6. Disclaimers & Limitation of Liability</h3>
                <div class="text-xs text-slate-500 leading-relaxed font-semibold space-y-4">
                    <p>
                        <strong>No Liability for Venue Conditions:</strong> TurfBooking does not warrant or guarantee that the physical conditions of the turf, pitch, court, lighting, or equipment provided by the venue meet any specific safety standards. You participate in sports activities at your own risk.
                    </p>
                    <p>
                        <strong>Physical Injury:</strong> TurfBooking, its founders, employees, and partners are not responsible for any physical injury, sickness, disability, or death suffered by players during their slots at the venues. Players are strongly encouraged to play safely and maintain their own personal insurance.
                    </p>
                    <p>
                        <strong>Platform Availability:</strong> While we aim to maintain 99.9% uptime, we do not warrant that our platform, scheduling APIs, or mobile apps will run uninterrupted, secure, or free from server errors or software bugs.
                    </p>
                </div>
            </div>

            <!-- Modifications & Termination -->
            <div class="space-y-4">
                <h3 class="text-lg font-black text-slate-900 tracking-tight">7. Modifications & Termination</h3>
                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                    We reserve the right to modify these terms at any time. When updates are published, the "Last Updated" metadata will change. Continued use of TurfBooking after terms have updated signifies your consent to the modified terms. We also reserve the right to suspend or terminate user accounts that violate guidelines or engage in booking fraud.
                </p>
            </div>

            <!-- Help Section -->
            <div class="text-center pt-6 space-y-3">
                <h3 class="text-sm font-bold text-slate-800">Questions about our Terms & Conditions?</h3>
                <p class="text-xs text-slate-500 leading-relaxed font-semibold max-w-lg mx-auto">
                    Please get in touch with our operations team if you need clarification on any aspect of this agreement.
                </p>
                <div class="pt-2">
                    <a href="{{ url('/contact') }}" class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-650 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition duration-150 shadow-md shadow-emerald-500/10">
                        Contact Us
                    </a>
                </div>
            </div>

        </div>
    </section>
</x-marketing-layout>
