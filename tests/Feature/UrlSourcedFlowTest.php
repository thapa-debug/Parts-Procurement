<?php

use App\Actions\CheckoutAction;
use App\Actions\MarkPurchasedFromUrlAction;
use App\Actions\SetUrlPriceAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CheckoutTotalChangedException;
use App\Livewire\Buyer\RequestDetail;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ShippingWeightBracket;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\DashboardMetricsService;
use App\Services\DhlRateCalculator;
use App\Services\ShippingFeeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// The reference-URL (admin-sourced) flow end to end: only the front half
// (SetUrlPriceAction) and the purchase step (MarkPurchasedFromUrlAction)
// are new -- checkout, payment, the §6.3 gate and the dashboard are the
// existing flow, exercised here unchanged.

beforeEach(function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 2]);
});

/**
 * A buyer's request priced from its reference URL: cost 45,000 → buyer
 * price 54,000, Regular shipping 8,000 (12 kg).
 *
 * @return array{0: PartRequest, 1: User, 2: BuyerAddress}
 */
function urlPricedForBuyer(): array
{
    $buyer = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($buyer)->create();
    $request = PartRequest::factory()->for($profile, 'buyer')->create([
        'status' => RequestStatus::New,
        'reference_url' => 'https://example.com/listing/123',
    ]);

    $request = app(SetUrlPriceAction::class)->execute(
        $request, urlCost: 45_000, weightKg: 12,
        shippingFeeOverride: 8_000, shippingFeeOverrideReason: 'Admin-only reason XYZ',
    );

    return [$request, $buyer, BuyerAddress::factory()->create(['buyer_id' => $profile->id])];
}

// --- the whole lifecycle, on the existing back half ------------------------------

it('runs the full URL path: price → checkout → paid → purchased from URL', function () {
    [$request, , $address] = urlPricedForBuyer();

    expect($request->status)->toBe(RequestStatus::Quoted);

    $request = app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Standard, expectedTotal: 62_000);

    expect($request->status)->toBe(RequestStatus::Paid)
        ->and($request->paymentGateOpen())->toBeTrue()
        ->and(Payment::query()->where('part_request_id', $request->id)->sole())
        ->amount->toBe(62_000)
        ->status->toBe(PaymentStatus::Confirmed);

    $request = app(MarkPurchasedFromUrlAction::class)->execute($request, 'Order #A-778');

    expect($request->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($request->is_url_sourced)->toBeTrue()
        ->and($request->vendors()->count())->toBe(0)
        ->and($request->confirmed_vendor_id)->toBeNull();
});

it('lets the buyer choose DHL at checkout, rated from the admin-entered weight', function () {
    [$request, , $address] = urlPricedForBuyer();

    $request = app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Dhl);

    expect($request->shipping_method)->toBe(ShippingMethod::Dhl)
        ->and($request->shipping_fee)->toBe(DhlRateCalculator::DUMMY_FEE)
        // The Regular snapshot is untouched by the buyer's choice.
        ->and($request->url_shipping_fee)->toBe(8_000)
        ->and(Payment::query()->where('part_request_id', $request->id)->sole()->amount)->toBe(54_000 + DhlRateCalculator::DUMMY_FEE);
});

it('asks DhlRateCalculator with the admin-entered weight and the destination', function () {
    [$request, , $address] = urlPricedForBuyer();

    $dhl = Mockery::mock(DhlRateCalculator::class);
    $dhl->shouldReceive('calculateForWeight')
        ->once()
        ->withArgs(fn (float $weightKg, BuyerAddress $destination) => $weightKg === 12.0 && $destination->is($address))
        ->andReturn(23_456);
    app()->instance(DhlRateCalculator::class, $dhl);

    expect(app(ShippingFeeService::class)->feeFor($request, ShippingMethod::Dhl, $address))->toBe(23_456);
});

it('keeps the Regular fee from its own snapshot even after shipping_fee was overwritten by a DHL checkout', function () {
    [$request, , $address] = urlPricedForBuyer();
    // What a started-then-cancelled DHL checkout leaves behind.
    $request->update(['shipping_fee' => DhlRateCalculator::DUMMY_FEE, 'shipping_method' => ShippingMethod::Dhl]);
    ShippingWeightBracket::query()->update(['fee' => 99_000]);

    expect(app(ShippingFeeService::class)->feesFor($request->fresh(), $address))->toBe([
        'standard' => 8_000,
        'dhl' => DhlRateCalculator::DUMMY_FEE,
    ]);
});

it('refuses checkout at a stale total, exactly as for a vendor order', function () {
    [$request, , $address] = urlPricedForBuyer();

    expect(fn () => app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Standard, expectedTotal: 61_999))
        ->toThrow(CheckoutTotalChangedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Quoted)
        ->and(Payment::query()->count())->toBe(0);
});

it('refuses checkout of a URL request that was never priced', function () {
    $request = PartRequest::factory()->create([
        'status' => RequestStatus::Quoted,
        'is_url_sourced' => true,
        'buyer_price' => null,
    ]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    expect(fn () => app(CheckoutAction::class)->execute($request, $address))
        ->toThrow(CheckoutNotAllowedException::class);
});

// --- dashboard: URL orders count exactly like vendor orders ------------------------

it('counts a paid URL order in revenue, margin and shipping on the dashboard, alongside vendor orders', function () {
    [$request, , $address] = urlPricedForBuyer();
    app(CheckoutAction::class)->execute($request, $address); // 54,000 + 8,000 shipping

    // A vendor-flow order already on the books: 30,000 cost → 36,000.
    $vendorOrder = PartRequest::factory()->create([
        'status' => RequestStatus::Paid, 'cost_price' => 30_000, 'buyer_price' => 36_000, 'shipping_fee' => 3_000,
    ]);
    Payment::factory()->create([
        'part_request_id' => $vendorOrder->id, 'status' => PaymentStatus::Confirmed, 'amount' => 39_000, 'paid_at' => now(),
    ]);

    $kpis = app(DashboardMetricsService::class)->kpis(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addMinute());

    expect($kpis->completedOrders)->toBe(2)
        ->and($kpis->paidOrders)->toBe(2)
        ->and($kpis->salesRevenue)->toBe(54_000 + 36_000)
        ->and($kpis->grossMargin)->toBe(9_000 + 6_000)
        ->and($kpis->shippingFees)->toBe(8_000 + 3_000);
});

it('still counts a URL order once it is marked purchased', function () {
    [$request, , $address] = urlPricedForBuyer();
    $request = app(CheckoutAction::class)->execute($request, $address);
    app(MarkPurchasedFromUrlAction::class)->execute($request);

    $kpis = app(DashboardMetricsService::class)->kpis(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addMinute());

    expect($kpis->completedOrders)->toBe(1)
        ->and($kpis->grossMargin)->toBe(9_000);
});

it('does not count a priced-but-unpaid URL request', function () {
    urlPricedForBuyer();

    $kpis = app(DashboardMetricsService::class)->kpis(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addMinute());

    expect($kpis->completedOrders)->toBe(0)
        ->and($kpis->salesRevenue)->toBe(0);
});

// --- isolation: the buyer never sees the URL cost or margin ----------------------

it('shows the buyer their own reference URL, never the URL cost, margin, or admin-internal notes', function () {
    [$request, $buyer, $address] = urlPricedForBuyer();
    $request = app(CheckoutAction::class)->execute($request, $address);
    app(MarkPurchasedFromUrlAction::class)->execute($request, 'Secret purchase ref 991');

    $component = Livewire::actingAs($buyer)->test(RequestDetail::class, ['partRequest' => $request]);

    $component->assertSee('https://example.com/listing/123')
        ->assertDontSee('45,000')
        ->assertDontSee('Admin-only reason XYZ')
        ->assertDontSee('Secret purchase ref 991');

    $snapshotJson = json_encode($component->snapshot);

    expect($snapshotJson)->not->toContain('cost_price')
        ->and($snapshotJson)->not->toContain('applied_rate')
        ->and($snapshotJson)->not->toContain('applied_min_fee')
        ->and($snapshotJson)->not->toContain('45000')
        ->and($snapshotJson)->not->toContain('url_shipping_fee_override_reason')
        ->and($snapshotJson)->not->toContain('Admin-only reason XYZ')
        ->and($snapshotJson)->not->toContain('url_purchase_reference');
});

it('never shows a URL-sourced request to any vendor -- it was never broadcast', function () {
    [$request] = urlPricedForBuyer();
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor)->create();

    expect($vendor->can('respond', $request))->toBeFalse();
});

// --- who may run the new steps ------------------------------------------------------

it('lets only an admin price from a URL or mark a URL purchase', function () {
    [$request, $buyer] = urlPricedForBuyer();
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();

    expect($admin->can('setUrlPrice', $request))->toBeTrue()
        ->and($admin->can('markPurchasedFromUrl', $request))->toBeTrue()
        ->and($buyer->can('setUrlPrice', $request))->toBeFalse()
        ->and($buyer->can('markPurchasedFromUrl', $request))->toBeFalse()
        ->and($vendor->can('setUrlPrice', $request))->toBeFalse()
        ->and($vendor->can('markPurchasedFromUrl', $request))->toBeFalse();
});
