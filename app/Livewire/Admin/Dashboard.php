<?php

namespace App\Livewire\Admin;

use App\Enums\DashboardPeriod;
use App\Models\PartRequest;
use App\Services\DashboardKpis;
use App\Services\DashboardMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The admin dashboard home (CLAUDE.md §14 Phase 5) -- phase 1: the KPI
 * cards row only. Thin by design: every figure comes from
 * DashboardMetricsService (two aggregate queries: this period and the
 * like-for-like previous one); this component only picks the period and
 * pairs each card's current figure with its previous one.
 */
class Dashboard extends Component
{
    /** A DashboardPeriod value; anything unknown falls back to the default. */
    public string $period = DashboardPeriod::DEFAULT->value;

    public function mount(): void
    {
        $this->authorize('viewDashboard', PartRequest::class);
    }

    public function selectPeriod(string $period): void
    {
        $this->authorize('viewDashboard', PartRequest::class);

        $this->period = (DashboardPeriod::tryFrom($period) ?? DashboardPeriod::DEFAULT)->value;
    }

    /**
     * The six cards, in display order. `change` is the % vs the previous
     * period, or null ("—") when there's nothing to compare against.
     *
     * @return list<array{key: string, value: int|null, change: float|null}>
     */
    protected function cards(DashboardKpis $current, DashboardKpis $previous): array
    {
        $card = fn (string $key, ?int $value, ?int $previousValue) => [
            'key' => $key,
            'value' => $value,
            'change' => DashboardMetricsService::percentChange($value, $previousValue),
        ];

        return [
            $card('sales_revenue', $current->salesRevenue, $previous->salesRevenue),
            $card('gross_margin', $current->grossMargin, $previous->grossMargin),
            $card('completed_orders', $current->completedOrders, $previous->completedOrders),
            $card('average_order_value', $current->averageOrderValue(), $previous->averageOrderValue()),
            $card('average_margin_per_order', $current->averageMarginPerOrder(), $previous->averageMarginPerOrder()),
            $card('shipping_fees', $current->shippingFees, $previous->shippingFees),
        ];
    }

    public function render(): View
    {
        $period = DashboardPeriod::tryFrom($this->period) ?? DashboardPeriod::DEFAULT;
        $now = CarbonImmutable::now();
        ['current' => $current, 'previous' => $previous] = app(DashboardMetricsService::class)->forPeriod($period, $now);

        return view('livewire.admin.dashboard', [
            'activePeriod' => $period,
            // Shown under the selector so "30D" etc. is never ambiguous.
            'range' => $period->range($now),
            'previousRange' => $period->previousRange($now),
            'cards' => $this->cards($current, $previous),
            'kpis' => $current,
        ])->title(__('admin.dashboard.title'))
            ->layoutData(['wide' => true]);
    }
}
