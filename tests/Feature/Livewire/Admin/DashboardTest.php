<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// "Now": 2026-11-11 10:00 JST. Default 30D window starts 2026-10-13 00:00;
// its previous window is 2026-09-13 00:00 - 2026-10-12 10:00.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Tokyo'));
});

function paidOrder(int $buyerPrice, int $costPrice, int $shippingFee, string $paidAt, bool $free = false): PartRequest
{
    $request = PartRequest::factory()->create([
        'status' => RequestStatus::Paid,
        'is_free' => $free,
        'buyer_price' => $buyerPrice,
        'cost_price' => $costPrice,
        'shipping_fee' => $shippingFee,
    ]);
    Payment::factory()->create([
        'part_request_id' => $request->id,
        'status' => PaymentStatus::Confirmed,
        'amount' => $buyerPrice + $shippingFee,
        'paid_at' => CarbonImmutable::parse($paidAt, 'Asia/Tokyo'),
    ]);

    return $request;
}

/**
 * @return array<string, array{value: int|null, change: float|null}>
 */
function cardsByKey(Testable $component): array
{
    return collect($component->viewData('cards'))
        ->mapWithKeys(fn ($card) => [$card['key'] => ['value' => $card['value'], 'change' => $card['change']]])
        ->all();
}

it('is admin-only', function () {
    Livewire::actingAs(User::factory()->buyer()->create())->test(Dashboard::class)->assertForbidden();
    Livewire::actingAs(User::factory()->vendor()->create())->test(Dashboard::class)->assertForbidden();
    Livewire::actingAs(User::factory()->admin()->create())->test(Dashboard::class)->assertOk();
});

it('shows the six KPI cards for the default 30-day period, with % change vs the previous 30 days', function () {
    // Current period (from 2026-10-13): two paid orders and one free order.
    paidOrder(60_000, 50_000, 8_000, '2026-11-05 12:00:00');
    paidOrder(40_000, 30_000, 4_000, '2026-10-20 09:00:00');
    paidOrder(0, 5_000, 0, '2026-11-01 15:00:00', free: true);
    // Previous period: one paid order.
    paidOrder(50_000, 42_000, 6_000, '2026-10-01 12:00:00');
    // Neither: checkout started, not confirmed.
    $pending = PartRequest::factory()->create(['status' => RequestStatus::AwaitingPayment, 'buyer_price' => 900_000, 'cost_price' => 800_000]);
    Payment::factory()->create(['part_request_id' => $pending->id, 'status' => PaymentStatus::Pending]);

    $component = Livewire::actingAs(User::factory()->admin()->create())->test(Dashboard::class);

    expect($component->get('period'))->toBe('30d')
        ->and(cardsByKey($component))->toBe([
            // 100,000 vs 50,000
            'sales_revenue' => ['value' => 100_000, 'change' => 100.0],
            // 10,000 + 10,000 - 5,000 absorbed = 15,000 vs 8,000
            'gross_margin' => ['value' => 15_000, 'change' => 87.5],
            // 3 (incl. the free one) vs 1
            'completed_orders' => ['value' => 3, 'change' => 200.0],
            // 100,000 / 2 paid = 50,000 vs 50,000
            'average_order_value' => ['value' => 50_000, 'change' => 0.0],
            // 15,000 / 3 = 5,000 vs 8,000
            'average_margin_per_order' => ['value' => 5_000, 'change' => -37.5],
            // 12,000 vs 6,000
            'shipping_fees' => ['value' => 12_000, 'change' => 100.0],
        ])
        // 15,000 / 100,000
        ->and($component->viewData('kpis')->marginPercent())->toBe(15.0);
});

it('shows no change ("—") when the previous period had nothing', function () {
    paidOrder(60_000, 50_000, 8_000, '2026-11-05 12:00:00');

    $cards = cardsByKey(Livewire::actingAs(User::factory()->admin()->create())->test(Dashboard::class));

    expect($cards['sales_revenue'])->toBe(['value' => 60_000, 'change' => null])
        ->and($cards['average_order_value'])->toBe(['value' => 60_000, 'change' => null]);
});

it('switches period and recomputes', function () {
    paidOrder(60_000, 50_000, 8_000, '2026-11-11 09:00:00'); // today
    paidOrder(40_000, 30_000, 4_000, '2026-11-08 09:00:00'); // within 7D, not today

    $component = Livewire::actingAs(User::factory()->admin()->create())->test(Dashboard::class);

    $component->call('selectPeriod', 'today');
    expect($component->get('period'))->toBe('today')
        ->and(cardsByKey($component)['sales_revenue']['value'])->toBe(60_000);

    $component->call('selectPeriod', '7d');
    expect(cardsByKey($component)['sales_revenue']['value'])->toBe(100_000);
});

it('falls back to the default period for an unknown value', function () {
    $component = Livewire::actingAs(User::factory()->admin()->create())->test(Dashboard::class)
        ->call('selectPeriod', 'forever');

    expect($component->get('period'))->toBe('30d');
});

// --- page, route, nav -------------------------------------------------------------

it('is the admin landing page at /admin, with Dashboard first in the nav, in the wide layout', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([__('app.nav.dashboard'), __('app.nav.requests')])
        ->assertSee('<main class="mx-auto max-w-[96rem] px-6 py-10">', false);

    expect(route('admin.dashboard', absolute: false))->toBe('/admin');
});

it('keeps buyers and vendors out of the dashboard route', function () {
    $this->actingAs(User::factory()->buyer()->create())->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->vendor()->create())->get(route('admin.dashboard'))->assertForbidden();
});

it('renders the six cards with formatted yen values, the period selector and the compared ranges', function () {
    paidOrder(60_000, 50_000, 8_000, '2026-11-05 12:00:00');
    paidOrder(40_000, 30_000, 4_000, '2026-10-20 09:00:00');
    paidOrder(50_000, 42_000, 6_000, '2026-10-01 12:00:00'); // previous period

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(Dashboard::class)
        ->assertSeeInOrder([
            __('admin.dashboard.cards.sales_revenue'), '¥100,000',
            __('admin.dashboard.cards.gross_margin'), '¥20,000', '20.0% of sales',
            __('admin.dashboard.cards.completed_orders'), '2',
            __('admin.dashboard.cards.average_order_value'), '¥50,000',
            __('admin.dashboard.cards.average_margin_per_order'), '¥10,000',
            __('admin.dashboard.cards.shipping_fees'), '¥12,000',
        ])
        ->assertSee('▲ 100.0%', false)
        ->assertSeeHtml("wire:click=\"selectPeriod('7d')\"")
        ->assertSee('13 Oct 2026 – 11 Nov 2026')
        ->assertSee('vs 13 Sep 2026 – 12 Oct 2026');
});

it('marks the selected period as pressed', function () {
    $html = Livewire::actingAs(User::factory()->admin()->create())
        ->test(Dashboard::class)
        ->call('selectPeriod', 'qtd')
        ->html();

    expect($html)->toMatch('/aria-pressed="true"[^>]*>\s*QTD/');
});

it('shows a negative margin with a proper minus sign, a falling change in red, and the free-order note', function () {
    paidOrder(10_000, 8_000, 0, '2026-11-05 12:00:00');            // +2,000
    paidOrder(0, 12_000, 0, '2026-11-06 12:00:00', free: true);     // -12,000 absorbed
    paidOrder(30_000, 20_000, 0, '2026-10-01 12:00:00');            // previous: +10,000

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(Dashboard::class)
        ->assertSee('−¥10,000')
        ->assertSee('incl. 1 free order (¥12,000 absorbed)')
        ->assertSee(__('admin.dashboard.paid_orders_only'))
        ->assertSee('▼', false);
});

it('shows "—" for averages and comparisons when there is no data', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(Dashboard::class)
        ->assertSee('¥0')
        ->assertSee('—')
        ->assertSee(__('admin.dashboard.no_comparison'));
});
