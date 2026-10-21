<?php

use App\Models\Turf;
use App\Services\HistoricDataService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public const COLORS = [
        'bookings_received' => '#6366f1', // indigo
        'booking_amount'    => '#8b5cf6', // violet
        'received_app'      => '#0ea5e9', // sky
        'received_location' => '#10b981', // emerald
        'slots_booked'      => '#f59e0b', // amber
        'cancellations'     => '#f43f5e', // rose
    ];

    #[Url]
    public string $view = 'month';     // day | week | month | year
    #[Url]
    public string $cursor = '';        // Y-m-d, default today
    #[Url]
    public string $dateBasis = 'play'; // play | booked
    #[Url]
    public array $metrics = ['bookings_received', 'booking_amount'];

    public function mount(): void
    {
        $this->normalise();
    }

    public function updated($property): void
    {
        $this->normalise();
    }

    protected function service(): HistoricDataService
    {
        return app(HistoricDataService::class);
    }

    protected function normalise(): void
    {
        if (!in_array($this->view, HistoricDataService::VIEWS, true)) {
            $this->view = 'month';
        }
        if (!in_array($this->dateBasis, HistoricDataService::DATE_BASES, true)) {
            $this->dateBasis = 'play';
        }
        $this->cursor = $this->service()->parseCursor($this->cursor)->format('Y-m-d');

        $metrics = array_values(array_intersect(array_keys(HistoricDataService::METRICS), (array) $this->metrics));
        $this->metrics = $metrics ?: ['bookings_received'];
    }

    public function setView(string $view): void
    {
        if (in_array($view, HistoricDataService::VIEWS, true)) {
            $this->view = $view;
        }
    }

    public function setDateBasis(string $basis): void
    {
        $this->dateBasis = in_array($basis, HistoricDataService::DATE_BASES, true) ? $basis : 'play';
    }

    public function toggleMetric(string $key): void
    {
        if (!array_key_exists($key, HistoricDataService::METRICS)) {
            return;
        }

        if (in_array($key, $this->metrics, true)) {
            if (count($this->metrics) <= 1) {
                return; // at least one metric always stays selected
            }
            $this->metrics = array_values(array_diff($this->metrics, [$key]));
        } else {
            $selected = array_merge($this->metrics, [$key]);
            // keep the canonical order
            $this->metrics = array_values(array_intersect(array_keys(HistoricDataService::METRICS), $selected));
        }
    }

    protected function shift(int $direction): string
    {
        $c = $this->service()->parseCursor($this->cursor);

        $shifted = match ($this->view) {
            'day'   => $c->addDays($direction),
            'week'  => $c->startOfWeek(Carbon::MONDAY)->addWeeks($direction),
            'year'  => $c->startOfYear()->addYears($direction),
            default => $c->startOfMonth()->addMonthsNoOverflow($direction),
        };

        return $shifted->format('Y-m-d');
    }

    public function previous(): void
    {
        $this->cursor = $this->shift(-1);
    }

    public function next(): void
    {
        if ($this->isCurrentPeriod()) {
            return;
        }
        $this->cursor = $this->shift(1);
    }

    public function today(): void
    {
        $this->cursor = Carbon::now(HistoricDataService::TZ)->format('Y-m-d');
    }

    public function isCurrentPeriod(): bool
    {
        $range = $this->service()->range($this->view, $this->cursor);

        return $range['end']->gte(Carbon::now(HistoricDataService::TZ));
    }

    /** @return array{0: int[], 1: string} */
    protected function scope(): array
    {
        $turfs = Turf::manageable(auth()->user())->pluck('name', 'id');
        $ids = $turfs->keys()->map(fn ($id) => (int) $id)->all();
        $active = (int) session('active_turf_id');

        if ($active && in_array($active, $ids, true)) {
            return [[$active], (string) $turfs[$active]];
        }

        return [$ids, 'All turfs'];
    }

    public function with(): array
    {
        [$turfIds, $scopeLabel] = $this->scope();
        $data = $this->service()->series($turfIds, $this->view, $this->cursor, $this->metrics, $this->dateBasis);

        $chartSeries = [];
        $isEmpty = true;
        foreach ($data['series'] as $key => $values) {
            $unit = HistoricDataService::METRICS[$key]['unit'];
            $chartSeries[] = [
                'key'   => $key,
                'name'  => HistoricDataService::METRICS[$key]['label'],
                'type'  => $unit === 'count' ? 'column' : 'line',
                'unit'  => $unit,
                'color' => self::COLORS[$key],
                'data'  => array_values($values),
            ];
            if (array_filter($values, fn ($v) => $v != 0)) {
                $isEmpty = false;
            }
        }

        $payload = [
            'categories' => array_values($data['range']['buckets']),
            'series' => $chartSeries,
            'cancellationValues' => isset($data['cancellations_value_series']) ? array_values($data['cancellations_value_series']) : null,
        ];

        $this->dispatch('historic-chart-updated', payload: $payload);

        return [
            'data' => $data,
            'payload' => $payload,
            'isEmpty' => $isEmpty,
            'scopeLabel' => $scopeLabel,
            'isCurrentPeriod' => $data['range']['end']->gte(Carbon::now(HistoricDataService::TZ)),
            'metricDefs' => HistoricDataService::METRICS,
            'colors' => self::COLORS,
        ];
    }

    public function dateHint(string $key): string
    {
        return match ($key) {
            'received_app', 'received_location' => 'by payment date',
            'cancellations' => 'by cancellation date',
            default => $this->dateBasis === 'booked' ? 'by booked-on date' : 'by day of play',
        };
    }
}; ?>

<div class="space-y-6" id="historic-data-page">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1">
                <a href="{{ route('turf.reports.overview') }}" wire:navigate class="hover:underline">Reports</a>
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-500 font-medium">Historic Data</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-7 h-7 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                Historic Data
            </h1>
            <p class="text-sm text-gray-500 mt-1 truncate">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ $scopeLabel }}</span>
                <span class="ms-1">Trends by day, week, month and year.</span>
            </p>
        </div>
    </div>

    <!-- Period bar -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-3 sm:p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <!-- View segmented control -->
            <div class="inline-flex rounded-xl bg-gray-100 p-1" role="tablist" aria-label="View">
                @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $v => $vLabel)
                    <button type="button" id="historic-view-{{ $v }}" wire:click="setView('{{ $v }}')" role="tab" aria-selected="{{ $view === $v ? 'true' : 'false' }}"
                        class="px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-semibold rounded-lg transition {{ $view === $v ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                        {{ $vLabel }}
                    </button>
                @endforeach
            </div>

            <!-- Date basis toggle -->
            <div class="inline-flex items-center gap-1.5" x-data="{ hint: false }">
                <div class="inline-flex rounded-xl bg-gray-100 p-1" role="group" aria-label="Date basis">
                    <button type="button" id="historic-basis-play" wire:click="setDateBasis('play')"
                        class="px-3 py-1.5 text-xs sm:text-sm font-semibold rounded-lg transition {{ $dateBasis === 'play' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">Day of play</button>
                    <button type="button" id="historic-basis-booked" wire:click="setDateBasis('booked')"
                        class="px-3 py-1.5 text-xs sm:text-sm font-semibold rounded-lg transition {{ $dateBasis === 'booked' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">Booked on</button>
                </div>
                <div class="relative">
                    <button type="button" id="historic-basis-hint" @click="hint = !hint" @click.outside="hint = false"
                        class="w-6 h-6 rounded-full text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 flex items-center justify-center transition"
                        title="Applies to Bookings, Booking Amount and Slots. Payments use the payment date, cancellations the cancellation date." aria-label="About date basis">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="hint" x-transition x-cloak
                        class="absolute z-20 mt-2 right-0 sm:left-0 sm:right-auto w-64 rounded-xl bg-gray-900 text-white text-xs leading-relaxed p-3 shadow-lg">
                        Applies to Bookings, Booking Amount and Slots. Payments use the payment date, cancellations the cancellation date.
                    </div>
                </div>
            </div>
        </div>

        <!-- Previous / title / Next / Today -->
        <div class="flex items-center justify-between lg:justify-end gap-2">
            <button type="button" id="historic-prev" wire:click="previous" aria-label="Previous period"
                class="w-9 h-9 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-indigo-600 flex items-center justify-center transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div class="min-w-0 flex-1 lg:flex-none lg:min-w-[11rem] text-center">
                <div class="text-sm sm:text-base font-bold text-gray-900 truncate" id="historic-period-title">{{ $data['range']['label'] }}</div>
            </div>
            <button type="button" id="historic-next" wire:click="next" aria-label="Next period" @disabled($isCurrentPeriod)
                class="w-9 h-9 rounded-xl border border-gray-200 flex items-center justify-center transition {{ $isCurrentPeriod ? 'text-gray-300 cursor-not-allowed bg-gray-50' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button type="button" id="historic-today" wire:click="today"
                class="ms-1 px-3 h-9 rounded-xl bg-indigo-50 text-indigo-700 text-xs sm:text-sm font-semibold hover:bg-indigo-100 transition">Today</button>
        </div>
    </div>

    <!-- Metric pills -->
    <div class="flex flex-wrap gap-2" aria-label="Metrics">
        @foreach ($metricDefs as $key => $def)
            @php $on = in_array($key, $metrics, true); @endphp
            <button type="button" id="historic-pill-{{ $key }}" wire:click="toggleMetric('{{ $key }}')"
                title="{{ $def['label'] }} — {{ $this->dateHint($key) }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs sm:text-sm font-semibold border transition active:scale-95"
                style="{{ $on ? 'background-color:' . $colors[$key] . ';border-color:' . $colors[$key] . ';color:#fff;' : 'background-color:#fff;border-color:#e5e7eb;color:#374151;' }}">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $on ? '#fff' : $colors[$key] }}"></span>
                {{ $def['label'] }}
            </button>
        @endforeach
    </div>

    <!-- Totals strip -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach ($data['totals'] as $key => $total)
            @continue($key === 'cancellations_value')
            <div class="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-3 sm:p-4 relative overflow-hidden" id="historic-total-{{ $key }}">
                <span class="absolute inset-x-0 top-0 h-1" style="background-color: {{ $colors[$key] }}"></span>
                <div class="flex items-center gap-1.5 text-[11px] sm:text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $colors[$key] }}"></span>
                    <span class="truncate">{{ $metricDefs[$key]['label'] }}</span>
                </div>
                <div class="mt-1.5 text-lg sm:text-xl font-bold text-gray-900 tabular-nums break-words">
                    @if ($metricDefs[$key]['unit'] === 'inr')
                        ₹{{ number_format($total, 2) }}
                    @else
                        {{ number_format($total) }}
                    @endif
                </div>
                @if ($key === 'bookings_received')
                    <div class="text-xs text-gray-500 mt-0.5">{{ number_format($total) }} {{ \Illuminate\Support\Str::plural('session', $total) }} · {{ number_format($data['distinct_bookings'] ?? 0) }} {{ \Illuminate\Support\Str::plural('booking', $data['distinct_bookings'] ?? 0) }}</div>
                @elseif ($key === 'cancellations')
                    <div class="text-xs text-gray-500 mt-0.5">₹{{ number_format($data['totals']['cancellations_value'] ?? 0, 2) }} cancelled</div>
                @else
                    <div class="text-xs text-gray-400 mt-0.5">{{ $this->dateHint($key) }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Chart -->
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-2 sm:p-4 relative">
        <div wire:ignore x-data="historicChart(@js($payload))" class="historic-chart w-full min-w-0" id="historic-chart">
            <div x-ref="canvas" class="h-[280px] sm:h-[380px]"></div>
        </div>
        @if ($isEmpty)
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none" id="historic-empty">
                <div class="bg-white/90 backdrop-blur-sm rounded-2xl px-5 py-4 text-center shadow-sm border border-gray-100">
                    <svg class="w-8 h-8 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <p class="mt-1 text-sm font-semibold text-gray-600">No activity in this period</p>
                </div>
            </div>
        @endif
        <div wire:loading.flex class="absolute inset-0 items-center justify-center bg-white/50 rounded-2xl">
            <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
        </div>
    </div>
</div>
