<?php

use App\Actions\PresentQuoteAction;
use App\Enums\RequestStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\Setting;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
