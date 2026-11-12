{{-- Admin dashboard, phase 1: the KPI cards row. Every figure comes from
DashboardMetricsService via the component -- this view only formats. --}}
@php
    // Yen with a proper minus sign for a negative margin (−¥3,000, not ¥-3,000).
    $yen = fn (?int $value) => $value === null ? '—' : ($value < 0 ? '−' : '').'¥'.number_format(abs($value));
    $dateFormat = 'j M Y';
@endphp

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">{{ __('admin.dashboard.title') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('admin.dashboard.subheading') }}</p>
        </div>

        <div class="flex flex-col items-end gap-1.5">
            <div class="inline-flex rounded-lg border border-line bg-surface p-0.5 shadow-sm" role="group" aria-label="{{ __('admin.dashboard.period_label') }}">
                @foreach (\App\Enums\DashboardPeriod::cases() as $option)
                    <button
                        type="button"
                        wire:click="selectPeriod('{{ $option->value }}')"
                        @if ($option === $activePeriod) aria-pressed="true" @else aria-pressed="false" @endif
                        @class([
                            'rounded-md px-3 py-1.5 text-xs font-semibold transition duration-150',
                            'bg-brand-600 text-white shadow-sm' => $option === $activePeriod,
                            'text-ink-muted hover:bg-surface-muted hover:text-ink' => $option !== $activePeriod,
                        ])
                    >
                        {{ __('admin.dashboard.periods.'.$option->value) }}
                    </button>
                @endforeach
            </div>
            <p class="text-xs text-ink-muted">
                {{ __('admin.dashboard.range', ['from' => $range[0]->format($dateFormat), 'to' => $range[1]->format($dateFormat)]) }}
                &middot;
                {{ __('admin.dashboard.compared_with', ['from' => $previousRange[0]->format($dateFormat), 'to' => $previousRange[1]->format($dateFormat)]) }}
            </p>
        </div>
    </div>

    <div
        class="mt-6 grid grid-cols-1 gap-4 transition-opacity sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6"
        wire:loading.class="opacity-60"
        wire:target="selectPeriod"
    >
        @foreach ($cards as $card)
            <div wire:key="kpi-{{ $card['key'] }}" class="flex flex-col rounded-lg border border-line bg-surface p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ __('admin.dashboard.cards.'.$card['key']) }}</p>

                <p @class([
                    'mt-2 text-2xl font-semibold tabular-nums',
                    'text-red-700' => $card['value'] !== null && $card['value'] < 0,
                    'text-ink' => $card['value'] === null || $card['value'] >= 0,
                ])>
                    {{ $card['key'] === 'completed_orders' ? number_format($card['value']) : $yen($card['value']) }}
                </p>

                {{-- Card-specific context, all from the same period's figures. --}}
                @if ($card['key'] === 'gross_margin' && $kpis->marginPercent() !== null)
                    <p class="mt-0.5 text-xs text-ink-muted">{{ __('admin.dashboard.margin_of_sales', ['percent' => number_format($kpis->marginPercent(), 1)]) }}</p>
                @elseif ($card['key'] === 'completed_orders' && $kpis->freeOrders > 0)
                    <p class="mt-0.5 text-xs text-ink-muted">{{ trans_choice('admin.dashboard.free_orders_note', $kpis->freeOrders, ['count' => $kpis->freeOrders, 'amount' => number_format($kpis->absorbedCost)]) }}</p>
                @elseif ($card['key'] === 'average_order_value' && $kpis->freeOrders > 0)
                    <p class="mt-0.5 text-xs text-ink-muted">{{ __('admin.dashboard.paid_orders_only') }}</p>
                @endif

                {{-- vs previous period: up is good for every card here. --}}
                <p class="mt-auto pt-3 text-xs">
                    @if ($card['change'] === null)
                        <span class="text-ink-muted">{{ __('admin.dashboard.no_comparison') }}</span>
                    @elseif ($card['change'] > 0)
                        <span class="font-semibold text-green-700">▲ {{ number_format($card['change'], 1) }}%</span>
                        <span class="text-ink-muted">{{ __('admin.dashboard.vs_previous') }}</span>
                    @elseif ($card['change'] < 0)
                        <span class="font-semibold text-red-700">▼ {{ number_format(abs($card['change']), 1) }}%</span>
                        <span class="text-ink-muted">{{ __('admin.dashboard.vs_previous') }}</span>
                    @else
                        <span class="font-semibold text-ink-muted">0.0%</span>
                        <span class="text-ink-muted">{{ __('admin.dashboard.vs_previous') }}</span>
                    @endif
                </p>
            </div>
        @endforeach
    </div>
</div>
