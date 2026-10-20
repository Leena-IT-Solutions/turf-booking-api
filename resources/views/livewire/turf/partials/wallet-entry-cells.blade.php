@props(['tx', 'part' => 'badge'])

@php
    $amt = (float) $tx->amount;
    $group = \App\Models\CommissionWalletTransaction::groupOf($tx->type);
    $colorClass = \App\Models\CommissionWalletTransaction::groupColor($group);
    $label = \App\Models\CommissionWalletTransaction::typeLabel($tx->type, $amt);
    $booking = \App\Services\WalletLedgerService::bookingFor($tx);
    $ref = $booking ? ($booking->booking_reference ?? '#' . $booking->id) : null;
    $turfName = \App\Services\WalletLedgerService::turfNameFor($tx);
@endphp

@if ($part === 'badge')
    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $colorClass }}">
        <span class="w-1.5 h-1.5 rounded-full {{ in_array($group, ['earnings', 'returns']) ? 'bg-emerald-500' : (in_array($group, ['deductions', 'refunds']) ? 'bg-rose-500' : ($group === 'payouts' ? 'bg-indigo-500' : 'bg-gray-400')) }}"></span>
        {{ $label }}
    </span>
@elseif ($part === 'booking')
    @if ($booking)
        <a href="{{ route('turf.bookings', ['search' => $ref]) }}" 
           class="text-indigo-600 hover:text-indigo-900 font-semibold font-mono text-xs hover:underline inline-flex items-center gap-1"
           title="View booking {{ $ref }}">
            {{ $ref }}
        </a>
    @elseif ($tx->reference instanceof \App\Models\TurfPayout || in_array($tx->type, ['payout_debit', 'payout_reversal'], true))
        <span class="font-mono text-xs text-gray-600 font-medium">Payout #{{ $tx->reference_id ?? $tx->id }}</span>
    @else
        <span class="text-gray-400 text-xs">—</span>
    @endif
@elseif ($part === 'turf')
    <span class="text-xs text-gray-700 font-medium">{{ $turfName }}</span>
@elseif ($part === 'booking-and-turf')
    <div>
        <div class="font-semibold text-xs">
            @if ($booking)
                <a href="{{ route('turf.bookings', ['search' => $ref]) }}" class="text-indigo-600 hover:text-indigo-900 font-mono hover:underline">{{ $ref }}</a>
            @elseif ($tx->reference instanceof \App\Models\TurfPayout || in_array($tx->type, ['payout_debit', 'payout_reversal'], true))
                <span class="font-mono text-gray-600">Payout #{{ $tx->reference_id ?? $tx->id }}</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </div>
        <div class="text-[11px] text-gray-500">{{ $turfName }}</div>
    </div>
@endif
