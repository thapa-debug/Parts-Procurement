<?php

namespace App\Services;

/**
 * One period's KPI totals for the admin dashboard (all money in integer
 * yen), plus the figures derived from them. Built by DashboardMetricsService
 * from a single aggregate query.
 *
 * Free (無償) orders, as agreed with the client:
 * - count as completed orders, with ¥0 revenue and ¥0 shipping;
 * - are included in gross margin at -cost_price (the absorbed vendor cost);
 * - are EXCLUDED from average order value (paid orders only), so ¥0
 *   orders don't drag the average sale down;
 * - are INCLUDED in average margin per order (spread across every
 *   completed order), since the absorbed cost is real.
 */
final readonly class DashboardKpis
{
    public function __construct(
        public int $salesRevenue,
        public int $grossMargin,
        public int $shippingFees,
        public int $completedOrders,
        public int $paidOrders,
        public int $freeOrders,
        public int $absorbedCost,
    ) {}

    /** Gross margin as a % of sales, or null with no sales. */
    public function marginPercent(): ?float
    {
        return $this->salesRevenue === 0 ? null : round($this->grossMargin / $this->salesRevenue * 100, 1);
    }

    /** Sales revenue ÷ paid (non-free) orders, in whole yen. */
    public function averageOrderValue(): ?int
    {
        return $this->paidOrders === 0 ? null : (int) round($this->salesRevenue / $this->paidOrders);
    }

    /** Gross margin ÷ every completed order (free included), in whole yen. */
    public function averageMarginPerOrder(): ?int
    {
        return $this->completedOrders === 0 ? null : (int) round($this->grossMargin / $this->completedOrders);
    }
}
