@if ($currentStep === 9)
    <!-- STEP 9: TAXATION & GST -->
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
            <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                    Step 9 of 12 · Required (State Selection)
                </span>
                <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Taxation, State & GST Configuration') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Select the Indian State where this turf is located (required for CGST/SGST vs IGST calculation) and configure optional GST invoicing.') }}</p>
            </div>

            <button type="button"
                    wire:click="copyLocationToTaxation"
                    class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition cursor-pointer shrink-0">
                📋 {{ __('Copy Name & Address from Location') }}
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Venue State (India)') }} <span class="text-rose-500">*</span></label>
                <select wire:model.live="taxState"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('-- Select State --') }}</option>
                    @foreach (\App\Support\IndianStates::ALL as $code => $stateName)
                        <option value="{{ $stateName }}">{{ $stateName }} ({{ $code }})</option>
                    @endforeach
                </select>
                @error('state') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                @error('taxState') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Legal / Company Name (Optional)') }}</label>
                <input type="text" wire:model="taxCompanyName" placeholder="e.g. Wankhede Sports Pvt Ltd" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900" />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Billing Email (Optional)') }}</label>
                <input type="email" wire:model="taxCompanyEmail" placeholder="billing@venue.com" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900" />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Billing Phone (Optional)') }}</label>
                <input type="text" wire:model="taxCompanyPhone" placeholder="9876543210" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono text-gray-900" />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('City') }}</label>
                <input type="text" wire:model="taxCity" placeholder="Mumbai" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900" />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Pincode') }}</label>
                <input type="text" wire:model="taxPincode" placeholder="400001" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono text-gray-900" />
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Registered Billing Address') }}</label>
                <input type="text" wire:model="taxAddress" placeholder="Full billing address for tax invoices" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900" />
            </div>
        </div>

        <!-- GST Toggle & Fields -->
        <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50/60 space-y-4">
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <input type="checkbox" wire:model.live="isGstBillingActive" class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                <div>
                    <span class="block text-sm font-black text-gray-900">{{ __('Enable GST Billing for Customer Bookings') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Turn ON only if this venue has a valid 15-character GSTIN registered in the same state.') }}</span>
                </div>
            </label>

            @if ($isGstBillingActive)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-gray-200/80">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('15-Digit GSTIN') }} <span class="text-rose-500">*</span></label>
                        <input type="text"
                               maxlength="15"
                               wire:model.live="gstNumber"
                               placeholder="27AAAAA0000A1Z5"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono uppercase text-gray-900 bg-white" />
                        @error('gst_number') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                        @error('gstNumber') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('GST Pricing Mode') }}</label>
                        <select wire:model="gstPricingType" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white text-gray-900">
                            <option value="included">{{ __('GST Included in Slot Price') }}</option>
                            <option value="excluded">{{ __('GST Excluded (Added on Top)') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('GST Rate (%)') }}</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model="gstPercentage" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono bg-white text-gray-900" />
                    </div>
                </div>
            @endif
        </div>
    </div>
@elseif ($currentStep === 10)
    <!-- STEP 10: BOOKING & PAYMENT RULES -->
    <div class="space-y-6">
        <div class="pb-4 border-b border-gray-100">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-gray-100 text-gray-700">
                Step 10 of 12 · Optional (Sensible Defaults Pre-Filled)
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Booking, Payment & Cancellation Rules') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Configure how players pay, how far in advance bookings open, and your cancellation/reschedule policies.') }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="p-4 rounded-2xl border-2 cursor-pointer flex items-start gap-3 {{ $isOnlinePaymentActive ? 'border-indigo-600 bg-indigo-50/30' : 'border-gray-200 bg-white' }}">
                <input type="checkbox" wire:model.live="isOnlinePaymentActive" class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600" />
                <div>
                    <span class="block text-xs font-black text-gray-900">{{ __('100% Online Payment') }}</span>
                    <span class="block text-[11px] text-gray-500 mt-0.5">{{ __('Players can pay the full booking amount online via UPI / Card.') }}</span>
                </div>
            </label>

            <label class="p-4 rounded-2xl border-2 cursor-pointer flex items-start gap-3 {{ $isPartPaymentActive ? 'border-indigo-600 bg-indigo-50/30' : 'border-gray-200 bg-white' }}">
                <input type="checkbox" wire:model.live="isPartPaymentActive" class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600" />
                <div>
                    <span class="block text-xs font-black text-gray-900">{{ __('Advance Part-Payment') }}</span>
                    <span class="block text-[11px] text-gray-500 mt-0.5">{{ __('Allow players to pay an advance online and balance at the venue.') }}</span>
                </div>
            </label>

            <label class="p-4 rounded-2xl border-2 cursor-pointer flex items-start gap-3 {{ $isPayAtLocationActive ? 'border-indigo-600 bg-indigo-50/30' : 'border-gray-200 bg-white' }}">
                <input type="checkbox" wire:model.live="isPayAtLocationActive" class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600" />
                <div>
                    <span class="block text-xs font-black text-gray-900">{{ __('Pay at Venue (Cash / UPI)') }}</span>
                    <span class="block text-[11px] text-gray-500 mt-0.5">{{ __('Allow players to reserve slots and pay directly at the turf.') }}</span>
                </div>
            </label>
        </div>

        @if ($isPartPaymentActive)
            <div class="p-4 rounded-2xl bg-indigo-50/40 border border-indigo-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('Part-Payment Type') }}</label>
                    <select wire:model.live="partPaymentType" class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-xs font-bold bg-white">
                        <option value="percentage">{{ __('Percentage (%) of Total Booking') }}</option>
                        <option value="flat">{{ __('Flat Advance Amount (₹)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        {{ $partPaymentType === 'percentage' ? __('Advance Percentage (%)') : __('Advance Flat Amount (₹)') }}
                    </label>
                    <input type="number" min="1" step="any" wire:model="partPaymentValue" class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-xs font-mono font-bold bg-white" />
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div class="p-4 rounded-2xl border border-gray-200 bg-white space-y-2">
                <label class="block text-xs font-black text-gray-900 uppercase tracking-wider">{{ __('Advance Booking Window') }}</label>
                <select wire:model="bookingOpenDays" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-bold bg-gray-50">
                    <option value="30">{{ __('30 Days in Advance') }}</option>
                    <option value="60">{{ __('60 Days in Advance') }}</option>
                    <option value="90">{{ __('90 Days in Advance') }}</option>
                </select>
            </div>

            <div class="p-4 rounded-2xl border border-gray-200 bg-white space-y-2.5">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="isCancellationActive" class="rounded border-gray-300 text-indigo-600" />
                    <span class="text-xs font-black text-gray-900 uppercase tracking-wider">{{ __('Allow Cancellations') }}</span>
                </label>
                @if ($isCancellationActive)
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div>
                            <span class="block text-[10px] font-bold text-gray-500">{{ __('Cutoff (Hours)') }}</span>
                            <input type="number" min="0" wire:model="cancellationHours" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-gray-500">{{ __('Fee (%)') }}</span>
                            <input type="number" min="0" max="100" step="any" wire:model="cancellationFee" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                        </div>
                    </div>
                @endif
            </div>

            <div class="p-4 rounded-2xl border border-gray-200 bg-white space-y-2.5">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="isRescheduleActive" class="rounded border-gray-300 text-indigo-600" />
                    <span class="text-xs font-black text-gray-900 uppercase tracking-wider">{{ __('Allow Rescheduling') }}</span>
                </label>
                @if ($isRescheduleActive)
                    <div class="pt-1">
                        <span class="block text-[10px] font-bold text-gray-500">{{ __('Max Reschedules per Booking') }}</span>
                        <input type="number" min="1" max="10" wire:model="maxReschedules" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                    </div>
                @endif
            </div>
        </div>
    </div>
@elseif ($currentStep === 11)
    <!-- STEP 11: PAYOUT ACCOUNT (OWNER ONLY) -->
    <div class="space-y-6">
        <div class="pb-4 border-b border-gray-100">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-gray-100 text-gray-700">
                Step 11 of 12 · Optional (Recommended for Online Bookings)
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Owner Payout Receiving Account (Bank / UPI)') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Enter the Turf Owner’s Bank Account or UPI ID so online booking earnings can be settled automatically.') }}</p>
        </div>

        @if ($isOnlinePaymentActive && empty($bankAccountNumber) && empty($upiId))
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold flex items-center justify-between gap-3">
                <span>⚠️ {{ __('Online payments are enabled in Step 10, but no payout destination is saved yet. You can enter it now or skip and add it later under Banking.') }}</span>
                <button type="button" wire:click="skipPayoutStep" class="px-3 py-1.5 rounded-xl bg-white border border-amber-300 text-amber-900 font-black text-[11px] uppercase shrink-0 cursor-pointer">
                    {{ __('Skip for Now →') }}
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md">
            <label class="p-3.5 rounded-xl border-2 cursor-pointer flex items-center gap-2.5 {{ $payoutMethod === 'bank' ? 'border-indigo-600 bg-indigo-50/40 font-black text-indigo-700' : 'border-gray-200 bg-white text-gray-700' }}">
                <input type="radio" wire:model.live="payoutMethod" value="bank" class="text-indigo-600" />
                <span class="text-xs">🏦 {{ __('Bank Account (NEFT / IMPS)') }}</span>
            </label>
            <label class="p-3.5 rounded-xl border-2 cursor-pointer flex items-center gap-2.5 {{ $payoutMethod === 'upi' ? 'border-indigo-600 bg-indigo-50/40 font-black text-indigo-700' : 'border-gray-200 bg-white text-gray-700' }}">
                <input type="radio" wire:model.live="payoutMethod" value="upi" class="text-indigo-600" />
                <span class="text-xs">📱 {{ __('UPI ID (VPA)') }}</span>
            </label>
        </div>

        @if ($payoutMethod === 'bank')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('Account Holder Name') }}</label>
                    <input type="text" wire:model="bankAccountName" placeholder="As per bank records" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900" />
                    @error('bankAccountName') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('Bank Account Number') }}</label>
                    <input type="text" wire:model="bankAccountNumber" placeholder="0000111122223333" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono text-gray-900" />
                    @error('bankAccountNumber') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('IFSC Code') }}</label>
                    <input type="text" wire:model="bankIfsc" placeholder="HDFC0001234" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono uppercase text-gray-900" />
                    @error('bankIfsc') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        @else
            <div class="max-w-md">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('UPI ID (VPA)') }}</label>
                <input type="text" wire:model="upiId" placeholder="ownername@okhdfcbank" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono text-gray-900" />
                @error('upiId') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>
@endif

