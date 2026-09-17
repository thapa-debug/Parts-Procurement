<?php

use App\Actions\PresentQuoteAction;
use App\Enums\RequestStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\Setting;
use App\Models\ShippingWeightBracket;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('presents a vendor quote, snapshotting the price onto a new presented_quotes row and transitioning to quoted', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    expect($presentedQuote->part_request_id)->toBe($request->id)
        ->and($presentedQuote->vendor_response_id)->toBe($response->id)
        ->and($presentedQuote->cost_price)->toBe(45_000)
        ->and($presentedQuote->applied_rate)->toBe(20)
        ->and($presentedQuote->applied_min_fee)->toBe(2000)
        // max(45000 * 20%, 2000) = max(9000, 2000) = 9000 -> 45000 + 9000
        ->and($presentedQuote->buyer_price)->toBe(54_000);

    expect($request->fresh()->status)->toBe(RequestStatus::Quoted)
        // Presenting alone never touches the buyer's pick -- that's
        // SelectQuoteAction's job, a separate step.
        ->and($request->fresh()->selected_response_id)->toBeNull();
});

it('presents the low-cost vendor\'s quote correctly when the percentage falls below the minimum fee floor', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 5_000,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    // max(5000 * 20%, 2000) = max(1000, 2000) = 2000 (floor wins) -> 5000 + 2000
    expect($presentedQuote->buyer_price)->toBe(7_000)
        ->and($presentedQuote->applied_min_fee)->toBe(2000);
});

it('snapshots only the presented vendor\'s price when multiple vendors responded, never a competitor\'s', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $cheapVendor = VendorProfile::factory()->create();
    $pricierVendor = VendorProfile::factory()->create();

    VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $cheapVendor->id,
        'cost_price' => 20_000,
    ]);
    $pricierResponse = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $pricierVendor->id,
        'cost_price' => 80_000,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $pricierResponse);

    expect($presentedQuote->cost_price)->toBe(80_000)
        ->and($presentedQuote->vendor_response_id)->toBe($pricierResponse->id);
});

it('never writes any vendor-identifying data onto the presented_quotes row -- only price fields and the response id', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create(['company_name' => 'Secret Vendor Co']);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 30_000,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    expect(array_keys($presentedQuote->getAttributes()))->not->toContain('vendor_id')
        ->and(collect($presentedQuote->getAttributes())->values()->implode(','))->not->toContain('Secret Vendor Co');
});

it('presents a second quote while the request is already quoted, without needing to re-transition status', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    app(PresentQuoteAction::class)->execute($request, $responseA);
    $request = $request->fresh();

    expect($request->status)->toBe(RequestStatus::Quoted);

    $presentedQuoteB = app(PresentQuoteAction::class)->execute($request, $responseB);

    expect($request->fresh()->status)->toBe(RequestStatus::Quoted)
        ->and(PresentedQuote::count())->toBe(2)
        ->and($presentedQuoteB->vendor_response_id)->toBe($responseB->id);
});

it('keeps an already-presented quote\'s snapshot unchanged when the margin rate changes before presenting another', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 40_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 40_000]);

    $presentedQuoteA = app(PresentQuoteAction::class)->execute($request, $responseA);

    Setting::set('margin_rate', 25, 'integer');

    $presentedQuoteB = app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    expect($presentedQuoteA->fresh()->applied_rate)->toBe(20)
        ->and($presentedQuoteA->fresh()->buyer_price)->toBe(48_000) // 40000 + max(8000, 2000)
        ->and($presentedQuoteB->applied_rate)->toBe(25)
        ->and($presentedQuoteB->buyer_price)->toBe(50_000); // 40000 + max(10000, 2000)
});

it('refuses to present a response that is already presented', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    app(PresentQuoteAction::class)->execute($request, $response);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request->fresh(), $response);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(1);
});

it('allows presenting another quote even while the buyer has a tentative, unpaid selection resting elsewhere', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    app(PresentQuoteAction::class)->execute($request, $responseA);

    // Simulates the buyer having tentatively selected quote A already
    // (SelectQuoteAction's own job -- not exercised here, just its effect).
    $request->fresh()->update(['selected_response_id' => $responseA->id, 'buyer_price' => 39_000]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    expect($attempt)->not->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(2);
});

it('refuses to present a quote unless the request is awaiting vendor responses or already showing presented quotes', function (RequestStatus $status) {
    $request = PartRequest::factory()->create(['status' => $status]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $response);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);

    expect($request->fresh()->status)->toBe($status)
        ->and(PresentedQuote::count())->toBe(0);
})->with([
    'still new' => RequestStatus::New,
    'paid' => RequestStatus::Paid,
    'ordered to vendor' => RequestStatus::OrderedToVendor,
    'procurement failed' => RequestStatus::ProcurementFailed,
    'shipped' => RequestStatus::Shipped,
    'received' => RequestStatus::Received,
]);

it('refuses a response that belongs to a different request', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $otherRequest = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $foreignResponse = VendorResponse::factory()->create([
        'part_request_id' => $otherRequest->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $foreignResponse);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect($request->fresh()->status)->toBe(RequestStatus::VendorInquiry)
        ->and(PresentedQuote::count())->toBe(0);
});

it('refuses to present a no-stock reply as a priced quote', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $noStockResponse = VendorResponse::factory()->noStock()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $noStockResponse);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect($request->fresh()->status)->toBe(RequestStatus::VendorInquiry)
        ->and(PresentedQuote::count())->toBe(0);
});

it('rolls back the whole presentation -- no status change, no new row -- if the transaction fails', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    PartRequest::updating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $response);

    expect($attempt)->toThrow(RuntimeException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::VendorInquiry)
        ->and(PresentedQuote::count())->toBe(0);
});

// --- shipping (CLAUDE.md §14 Phase 4: rule-based v1) ----------------------

it('auto-calculates the shipping fee from the response\'s own weight and snapshots it, not overridden', function () {
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 1]);
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 120_000, 'order' => 2]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    expect($presentedQuote->shipping_fee)->toBe(8_000)
        ->and($presentedQuote->shipping_fee_overridden)->toBeFalse()
        ->and($presentedQuote->shipping_fee_override_reason)->toBeNull();
});

it('lets the admin override the calculated shipping fee, given a reason -- stored and activity-logged', function () {
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 1]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute(
        $request,
        $response,
        shippingFeeOverride: 50_000,
        shippingFeeOverrideReason: 'Oversized crate required for this part.',
    );

    expect($presentedQuote->shipping_fee)->toBe(50_000)
        ->and($presentedQuote->shipping_fee_overridden)->toBeTrue()
        ->and($presentedQuote->shipping_fee_override_reason)->toBe('Oversized crate required for this part.');

    $activity = Activity::query()
        ->where('subject_type', PresentedQuote::class)
        ->where('subject_id', $presentedQuote->id)
        ->where('event', 'created')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['shipping_fee'])->toBe(50_000)
        ->and($activity->attribute_changes['attributes']['shipping_fee_overridden'])->toBeTrue()
        ->and($activity->attribute_changes['attributes']['shipping_fee_override_reason'])->toBe('Oversized crate required for this part.');
});

it('refuses an override with no reason', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $response, shippingFeeOverride: 50_000, shippingFeeOverrideReason: null);
    $blankAttempt = fn () => app(PresentQuoteAction::class)->execute($request, $response, shippingFeeOverride: 50_000, shippingFeeOverrideReason: '   ');

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect($blankAttempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(0);
});

it('refuses to present a response with no weight recorded', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => null,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $response);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(0);
});

it('keeps an already-presented quote\'s shipping fee unchanged when the weight brackets change afterward', function () {
    ShippingWeightBracket::query()->delete();
    $bracket = ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 1]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);
    expect($presentedQuote->shipping_fee)->toBe(8_000);

    $bracket->update(['fee' => 99_000]);

    expect($presentedQuote->fresh()->shipping_fee)->toBe(8_000);
});

// --- 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5) ---------------------

it('forces buyer_price and shipping_fee to zero on a free quote, while cost_price/applied_rate/applied_min_fee stay real', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 1]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);

    expect($presentedQuote->buyer_price)->toBe(0)
        ->and($presentedQuote->shipping_fee)->toBe(0)
        ->and($presentedQuote->is_free)->toBeTrue()
        // The real, normally-computed figures -- the admin still owes the
        // vendor 45,000 + margin, even though the buyer pays nothing.
        ->and($presentedQuote->cost_price)->toBe(45_000)
        ->and($presentedQuote->applied_rate)->toBe(20)
        ->and($presentedQuote->applied_min_fee)->toBe(2000);
});

it('refuses to combine a free quote with a shipping fee override', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute(
        $request,
        $response,
        shippingFeeOverride: 50_000,
        shippingFeeOverrideReason: 'Oversized crate required for this part.',
        isFree: true,
    );

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(0);
});

it('refuses to present a free quote when the request already has a paid presented quote', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000, 'weight_kg' => 5]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000, 'weight_kg' => 5]);

    app(PresentQuoteAction::class)->execute($request, $responseA);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request->fresh(), $responseB, isFree: true);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(1);
});

it('refuses to present a paid quote when the request already has a free presented quote', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000, 'weight_kg' => 5]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000, 'weight_kg' => 5]);

    app(PresentQuoteAction::class)->execute($request, $responseA, isFree: true);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(1);
});

it('still requires a recorded weight for a free quote, even though shipping is free', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => null,
    ]);

    $attempt = fn () => app(PresentQuoteAction::class)->execute($request, $response, isFree: true);

    expect($attempt)->toThrow(PresentQuoteNotAllowedException::class);
    expect(PresentedQuote::count())->toBe(0);
});

it('activity-logs is_free on a free presented quote', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);

    $activity = Activity::query()
        ->where('subject_type', PresentedQuote::class)
        ->where('subject_id', $presentedQuote->id)
        ->where('event', 'created')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['is_free'])->toBeTrue();
});
