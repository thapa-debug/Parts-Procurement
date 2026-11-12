<?php

namespace App\Services;

use App\Enums\DashboardPeriod;
use App\Enums\PaymentStatus;
use App\Models\PartRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * The admin dashboard's KPI figures (CLAUDE.md §14 Phase 5), computed with
 * one aggregate query per period -- never a per-row loop.
 *
 * Which orders count: a part_request with a CONFIRMED payment whose
 * paid_at falls in the period -- the payment half of the pay-before-
 * purchase gate (PartRequest::paymentGateOpen(), CLAUDE.md §6.3). So:
 * - awaiting_payment requests (checkout started, Stripe webhook not yet
 *   confirmed) are excluded: their payment is still pending;
 * - free (無償) orders count: ConfirmFreeOrderAction gives them a genuine
 *   ¥0 confirmed payment; is_free separates them from paid ones;
 * - procurement_failed requests still count: the money was received and
 *   is non-refundable for launch (CLAUDE.md §6.4).
 * It is an EXISTS subquery, not a join, so a request can never be counted
 * twice (e.g. a failed payment row left behind by a resumed checkout).
 *
 * Figures come from the snapshot columns on part_requests (buyer_price,
 * cost_price, shipping_fee -- CLAUDE.md §6.2), never recomputed from live
 * settings. Sales revenue is the part price only; shipping is its own KPI.
 * Soft-deleted requests are excluded (Eloquent's default scope).
 */
class DashboardMetricsService
{
    /**
     * The period's KPIs and its like-for-like previous period's, for the
     * vs-previous comparison (two aggregate queries in total).
     *
     * @return array{current: DashboardKpis, previous: DashboardKpis}
     */
    public function forPeriod(DashboardPeriod $period, CarbonImmutable $now): array
    {
        return [
            'current' => $this->kpis(...$period->range($now)),
            'previous' => $this->kpis(...$period->previousRange($now)),
        ];
    }

    /** KPI totals for orders whose payment was confirmed in [from, to). */
    public function kpis(CarbonImmutable $from, CarbonImmutable $to): DashboardKpis
    {
        $row = PartRequest::query()
            ->whereExists(function (Builder $query) use ($from, $to) {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.part_request_id', 'part_requests.id')
                    ->where('payments.status', PaymentStatus::Confirmed->value)
                    ->where('payments.paid_at', '>=', $from)
                    ->where('payments.paid_at', '<', $to);
            })
            ->toBase()
            ->selectRaw('COUNT(*) AS completed_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_free = 1 THEN 0 ELSE 1 END), 0) AS paid_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END), 0) AS free_orders')
            ->selectRaw('COALESCE(SUM(buyer_price), 0) AS sales_revenue')
            // buyer_price/cost_price are UNSIGNED: on MySQL/MariaDB a plain
            // `buyer_price - cost_price` is unsigned arithmetic and throws
            // "BIGINT UNSIGNED value is out of range" for a free order's
            // negative margin (0 - cost). Cast to SIGNED first. (sqlite has
            // no unsigned types, so only a real MySQL run shows this.)
            ->selectRaw('COALESCE(SUM(CAST(buyer_price AS SIGNED) - CAST(cost_price AS SIGNED)), 0) AS gross_margin')
            ->selectRaw('COALESCE(SUM(shipping_fee), 0) AS shipping_fees')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_free = 1 THEN cost_price ELSE 0 END), 0) AS absorbed_cost')
            ->first();

        return new DashboardKpis(
            salesRevenue: (int) $row->sales_revenue,
            grossMargin: (int) $row->gross_margin,
            shippingFees: (int) $row->shipping_fees,
            completedOrders: (int) $row->completed_orders,
            paidOrders: (int) $row->paid_orders,
            freeOrders: (int) $row->free_orders,
            absorbedCost: (int) $row->absorbed_cost,
        );
    }

    /**
     * % change from the previous period's figure, or null ("—") when there
     * is nothing to compare against (previous is null or zero). Relative to
     * |previous|, so a margin moving from negative towards positive reads
     * as a rise.
     */
    public static function percentChange(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
