<?php

use App\Enums\DashboardPeriod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\DashboardKpis;
use App\Services\DashboardMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// "Now" for every test: 2026-11-11 10:00 JST (the app timezone).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Tokyo'));
    $this->from = CarbonImmutable::parse('2026-11-01 00:00:00', 'Asia/Tokyo');
    $this->to = CarbonImmutable::now();
});

/**
 * A request with its snapshot figures and one payment. Defaults to a paid
 * (有償) order confirmed on 2026-11-05.
 *
 * @param  array<string, mixed>  $request
 */
function orderWith(array $request, PaymentStatus $paymentStatus = PaymentStatus::Confirmed, string $paidAt = '2026-11-05 12:00:00', int $amount = 1): PartRequest
{
    $partRequest = PartRequest::factory()->create([
        'status' => RequestStatus::Paid,
        'is_free' => false,
        'shipping_fee' => 0,
        ...$request,
    ]);

    Payment::factory()->create([
        'part_request_id' => $partRequest->id,
        'status' => $paymentStatus,
        'amount' => $amount,
        'paid_at' => $paymentStatus === PaymentStatus::Confirmed ? CarbonImmutable::parse($paidAt, 'Asia/Tokyo') : null,
    ]);

    return $partRequest;
}

function kpis(): DashboardKpis
{
    return app(DashboardMetricsService::class)->kpis(test()->from, test()->to);
}

// --- which orders count ------------------------------------------------------------

it('sums revenue from confirmed-paid orders only', function () {
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000]);
    orderWith(['buyer_price' => 30_000, 'cost_price' => 25_000]);
    // Not revenue: checkout started but never confirmed, a declined payment,
    // and a quoted request with no payment at all.
    orderWith(['status' => RequestStatus::AwaitingPayment, 'buyer_price' => 99_000, 'cost_price' => 80_000], PaymentStatus::Pending);
    orderWith(['status' => RequestStatus::AwaitingPayment, 'buyer_price' => 77_000, 'cost_price' => 60_000], PaymentStatus::Failed);
    PartRequest::factory()->create(['status' => RequestStatus::Quoted, 'buyer_price' => 88_000, 'cost_price' => 70_000]);

    $kpis = kpis();

    expect($kpis->salesRevenue)->toBe(84_000)
        ->and($kpis->completedOrders)->toBe(2)
        ->and($kpis->paidOrders)->toBe(2);
});

it('never counts an awaiting_payment request as revenue, however large', function () {
    orderWith(['status' => RequestStatus::AwaitingPayment, 'buyer_price' => 500_000, 'cost_price' => 400_000, 'shipping_fee' => 20_000], PaymentStatus::Pending);

    $kpis = kpis();

    expect($kpis->salesRevenue)->toBe(0)
        ->and($kpis->grossMargin)->toBe(0)
        ->and($kpis->shippingFees)->toBe(0)
        ->and($kpis->completedOrders)->toBe(0);
});

it('decides on the payment status, not on paid_at -- an unconfirmed payment never counts even if it carries a date', function (PaymentStatus $status) {
    $request = PartRequest::factory()->create(['status' => RequestStatus::AwaitingPayment, 'buyer_price' => 54_000, 'cost_price' => 45_000]);
    Payment::factory()->create([
        'part_request_id' => $request->id,
        'status' => $status,
        'amount' => 62_000,
        'paid_at' => CarbonImmutable::parse('2026-11-05 12:00:00', 'Asia/Tokyo'),
    ]);

    expect(kpis())
        ->salesRevenue->toBe(0)
        ->completedOrders->toBe(0);
})->with([PaymentStatus::Pending, PaymentStatus::Failed]);

it('still counts a paid order whose procurement later failed -- the money was received', function () {
    orderWith(['status' => RequestStatus::ProcurementFailed, 'buyer_price' => 54_000, 'cost_price' => 45_000]);

    expect(kpis()->salesRevenue)->toBe(54_000);
});

it('counts every post-payment status', function (RequestStatus $status) {
    orderWith(['status' => $status, 'buyer_price' => 10_000, 'cost_price' => 8_000]);

    expect(kpis()->completedOrders)->toBe(1);
})->with([RequestStatus::Paid, RequestStatus::OrderedToVendor, RequestStatus::Shipped, RequestStatus::Received]);

it('counts a request once even if it also has a failed payment from an earlier attempt', function () {
    $request = orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Failed]);

    expect(kpis())
        ->salesRevenue->toBe(54_000)
        ->completedOrders->toBe(1);
});

it('excludes soft-deleted requests', function () {
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000])->delete();

    expect(kpis()->completedOrders)->toBe(0);
});

// --- margin, shipping, snapshots ------------------------------------------------------

it('computes gross margin as buyer_price minus cost_price, and margin as a % of sales', function () {
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000]); // +9,000
    orderWith(['buyer_price' => 7_000, 'cost_price' => 5_000]);   // +2,000 (min-fee floor)

    $kpis = kpis();

    expect($kpis->grossMargin)->toBe(11_000)
        // 11,000 / 61,000 = 18.03...%
        ->and($kpis->marginPercent())->toBe(18.0);
});

it('sums shipping fees collected, separately from sales revenue', function () {
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000, 'shipping_fee' => 8_000]);
    orderWith(['buyer_price' => 30_000, 'cost_price' => 25_000, 'shipping_fee' => 15_000]);

    expect(kpis())
        ->shippingFees->toBe(23_000)
        ->salesRevenue->toBe(84_000);
});

it('uses the snapshotted figures on the request, not live settings', function () {
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000, 'applied_rate' => 20]);

    // A later margin change must not move an existing order's figures.
    Setting::set('margin_rate', 50, 'integer');

    expect(kpis())
        ->salesRevenue->toBe(54_000)
        ->grossMargin->toBe(9_000);
});

// --- free (無償) orders -------------------------------------------------------------

it('counts free orders as completed with ¥0 revenue, and their absorbed cost as negative margin', function () {
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000, 'shipping_fee' => 8_000]);
    // Free: buyer pays ¥0 for part and shipping; vendor cost 12,000 absorbed.
    orderWith(['is_free' => true, 'buyer_price' => 0, 'cost_price' => 12_000, 'shipping_fee' => 0], amount: 0);

    $kpis = kpis();

    expect($kpis->completedOrders)->toBe(2)
        ->and($kpis->paidOrders)->toBe(1)
        ->and($kpis->freeOrders)->toBe(1)
        ->and($kpis->salesRevenue)->toBe(54_000)
        ->and($kpis->shippingFees)->toBe(8_000)
        // 9,000 earned - 12,000 absorbed
        ->and($kpis->grossMargin)->toBe(-3_000)
        ->and($kpis->absorbedCost)->toBe(12_000);
});

it('subtracts unsigned money columns without overflowing when a row\'s margin is negative', function () {
    // Regression: buyer_price/cost_price are UNSIGNED, and on MySQL
    // `buyer_price - cost_price` is unsigned arithmetic -- a free order
    // (0 - 12,000) threw "BIGINT UNSIGNED value is out of range" instead of
    // giving -12,000. Invisible on sqlite, which has no unsigned types.
    orderWith(['is_free' => true, 'buyer_price' => 0, 'cost_price' => 12_000], amount: 0);
    orderWith(['is_free' => true, 'buyer_price' => 0, 'cost_price' => 3_000], amount: 0);

    expect(kpis())
        ->grossMargin->toBe(-15_000)
        ->absorbedCost->toBe(15_000)
        ->averageMarginPerOrder()->toBe(-7_500);

    // And mixed in with a positive-margin order.
    orderWith(['buyer_price' => 54_000, 'cost_price' => 45_000]);

    expect(kpis()->grossMargin)->toBe(-6_000);
});

it('leaves free orders out of average order value, but spreads margin across every completed order', function () {
    orderWith(['buyer_price' => 60_000, 'cost_price' => 50_000]);
    orderWith(['buyer_price' => 40_000, 'cost_price' => 30_000]);
    orderWith(['is_free' => true, 'buyer_price' => 0, 'cost_price' => 5_000], amount: 0);

    $kpis = kpis();

    // AOV: 100,000 / 2 paid orders -- not / 3.
    expect($kpis->averageOrderValue())->toBe(50_000)
        // Margin: (10,000 + 10,000 - 5,000) / 3 completed orders.
        ->and($kpis->averageMarginPerOrder())->toBe(5_000);
});

// --- averages -----------------------------------------------------------------------

it('rounds the averages to whole yen', function () {
    orderWith(['buyer_price' => 33_333, 'cost_price' => 30_000]);
    orderWith(['buyer_price' => 33_333, 'cost_price' => 30_000]);
    orderWith(['buyer_price' => 33_335, 'cost_price' => 30_000]);

    $kpis = kpis();

    // 100,001 / 3 = 33,333.67 -> 33,334; 10,001 / 3 = 3,333.67 -> 3,334
    expect($kpis->averageOrderValue())->toBe(33_334)
        ->and($kpis->averageMarginPerOrder())->toBe(3_334);
});

it('returns zeros and no averages for an empty period -- never a division by zero', function () {
    $kpis = kpis();

    expect($kpis->salesRevenue)->toBe(0)
        ->and($kpis->completedOrders)->toBe(0)
        ->and($kpis->averageOrderValue())->toBeNull()
        ->and($kpis->averageMarginPerOrder())->toBeNull()
        ->and($kpis->marginPercent())->toBeNull();
});

it('has no average order value when every order in the period was free', function () {
    orderWith(['is_free' => true, 'buyer_price' => 0, 'cost_price' => 5_000], amount: 0);

    $kpis = kpis();

    expect($kpis->averageOrderValue())->toBeNull()
        ->and($kpis->marginPercent())->toBeNull()
        ->and($kpis->averageMarginPerOrder())->toBe(-5_000);
});

// --- periods ------------------------------------------------------------------------

it('attributes an order to the period its payment was confirmed in -- [from, to)', function () {
    orderWith(['buyer_price' => 1_000, 'cost_price' => 500], paidAt: '2026-11-01 00:00:00'); // on from: in
    orderWith(['buyer_price' => 2_000, 'cost_price' => 500], paidAt: '2026-10-31 23:59:59'); // before: out
    orderWith(['buyer_price' => 4_000, 'cost_price' => 500], paidAt: '2026-11-11 10:00:00'); // on to: out

    expect(kpis()->salesRevenue)->toBe(1_000);
});

it('runs a single query however many orders there are', function () {
    foreach (range(1, 5) as $i) {
        orderWith(['buyer_price' => 10_000, 'cost_price' => 8_000]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    kpis();
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('defines each period and its like-for-like previous window in JST', function (DashboardPeriod $period, string $from, string $prevFrom, string $prevTo) {
    $now = CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Tokyo');
    $fmt = fn (CarbonImmutable $moment) => $moment->format('Y-m-d H:i');

    [$currentFrom, $currentTo] = $period->range($now);
    [$previousFrom, $previousTo] = $period->previousRange($now);

    expect($fmt($currentFrom))->toBe($from)
        ->and($fmt($currentTo))->toBe('2026-11-11 10:00')
        ->and($fmt($previousFrom))->toBe($prevFrom)
        ->and($fmt($previousTo))->toBe($prevTo);
})->with([
    'today' => [DashboardPeriod::Today, '2026-11-11 00:00', '2026-11-10 00:00', '2026-11-10 10:00'],
    '7 days' => [DashboardPeriod::SevenDays, '2026-11-05 00:00', '2026-10-29 00:00', '2026-11-04 10:00'],
    '30 days' => [DashboardPeriod::ThirtyDays, '2026-10-13 00:00', '2026-09-13 00:00', '2026-10-12 10:00'],
    'quarter to date' => [DashboardPeriod::QuarterToDate, '2026-10-01 00:00', '2026-07-01 00:00', '2026-08-11 10:00'],
    'year to date' => [DashboardPeriod::YearToDate, '2026-01-01 00:00', '2025-01-01 00:00', '2025-11-11 10:00'],
]);

it('compares periods as a % change, and gives no change when there is nothing to compare against', function () {
    expect(DashboardMetricsService::percentChange(112_400, 100_000))->toBe(12.4)
        ->and(DashboardMetricsService::percentChange(90_000, 100_000))->toBe(-10.0)
        ->and(DashboardMetricsService::percentChange(5_000, 0))->toBeNull()
        ->and(DashboardMetricsService::percentChange(null, 100))->toBeNull()
        ->and(DashboardMetricsService::percentChange(100, null))->toBeNull()
        // Margin moving from -10,000 to +5,000 reads as a rise.
        ->and(DashboardMetricsService::percentChange(5_000, -10_000))->toBe(150.0);
});
