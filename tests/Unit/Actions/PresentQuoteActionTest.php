<?php

use App\Actions\PresentQuoteAction;
use App\Enums\RequestStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\Setting;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('presents a vendor quote, snapshotting the price and transitioning to quoted', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    $result = app(PresentQuoteAction::class)->execute($request, $response);

    expect($result->status)->toBe(RequestStatus::Quoted)
        ->and($result->selected_response_id)->toBe($response->id)
        ->and($result->cost_price)->toBe(45_000)
        ->and($result->applied_rate)->toBe(20)
        ->and($result->applied_min_fee)->toBe(2000)
        // max(45000 * 20%, 2000) = max(9000, 2000) = 9000 -> 45000 + 9000
        ->and($result->buyer_price)->toBe(54_000);
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

    $result = app(PresentQuoteAction::class)->execute($request, $response);

    // max(5000 * 20%, 2000) = max(1000, 2000) = 2000 (floor wins) -> 5000 + 2000
    expect($result->buyer_price)->toBe(7_000)
        ->and($result->applied_min_fee)->toBe(2000);
});

it('snapshots only the selected vendor\'s price when multiple vendors responded, never a competitor\'s', function () {
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

    $result = app(PresentQuoteAction::class)->execute($request, $pricierResponse);

    expect($result->cost_price)->toBe(80_000)
        ->and($result->selected_response_id)->toBe($pricierResponse->id);
});

it('never writes any vendor-identifying data onto the part_request -- only price fields and the response id', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create(['company_name' => 'Secret Vendor Co']);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 30_000,
    ]);

    $result = app(PresentQuoteAction::class)->execute($request, $response);

    expect(array_keys($result->getChanges()))->not->toContain('vendor_id', 'confirmed_vendor_id')
        ->and(collect($result->getAttributes())->values()->implode(','))->not->toContain('Secret Vendor Co');
});

it('refuses to present a quote unless the request is awaiting vendor responses', function (RequestStatus $status) {
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
        ->and($request->fresh()->selected_response_id)->toBeNull();
})->with([
    'still new' => RequestStatus::New,
    'already quoted' => RequestStatus::Quoted,
    'paid' => RequestStatus::Paid,
    'ordered to vendor' => RequestStatus::OrderedToVendor,
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
    expect($request->fresh()->status)->toBe(RequestStatus::VendorInquiry);
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
    expect($request->fresh()->status)->toBe(RequestStatus::VendorInquiry);
});

it('rolls back the whole presentation -- no status change, no snapshot -- if the transaction fails', function () {
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

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::VendorInquiry)
        ->and($fresh->selected_response_id)->toBeNull()
        ->and($fresh->buyer_price)->toBeNull();
});
