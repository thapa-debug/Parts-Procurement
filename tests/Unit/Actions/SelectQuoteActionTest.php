<?php

use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\SelectQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\ShippingWeightBracket;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('selects a presented quote, copying its snapshot onto the part_request', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    $result = app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    expect($result->selected_response_id)->toBe($response->id)
        ->and($result->cost_price)->toBe($presentedQuote->cost_price)
        ->and($result->applied_rate)->toBe($presentedQuote->applied_rate)
        ->and($result->applied_min_fee)->toBe($presentedQuote->applied_min_fee)
        ->and($result->buyer_price)->toBe($presentedQuote->buyer_price);
});

it('also copies the presented quote\'s shipping fee, and sets shipping_method to Standard (CLAUDE.md §14 Phase 4)', function () {
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 10,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    $result = app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    expect($result->shipping_fee)->toBe(8_000)
        ->and($result->shipping_fee)->toBe($presentedQuote->shipping_fee)
        ->and($result->shipping_method)->toBe(ShippingMethod::Standard);
});

it('lets the buyer re-select a different presented quote, overwriting the previous pick, without throwing', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    $presentedQuoteA = app(PresentQuoteAction::class)->execute($request, $responseA);
    $presentedQuoteB = app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuoteA);
    expect($request->fresh()->selected_response_id)->toBe($responseA->id);

    $attempt = fn () => app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuoteB);

    expect($attempt)->not->toThrow(SelectQuoteNotAllowedException::class);
    expect($request->fresh()->selected_response_id)->toBe($responseB->id)
        ->and($request->fresh()->buyer_price)->toBe($presentedQuoteB->buyer_price);
});

it('allows selecting the same quote again without error', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);
    $attempt = fn () => app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    expect($attempt)->not->toThrow(SelectQuoteNotAllowedException::class);
    expect($request->fresh()->selected_response_id)->toBe($response->id);
});

it('never deletes or alters the other presented quotes when one is selected', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    $presentedQuoteA = app(PresentQuoteAction::class)->execute($request, $responseA);
    $presentedQuoteB = app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuoteA);

    expect(PresentedQuote::count())->toBe(2)
        ->and(PresentedQuote::find($presentedQuoteA->id))->not->toBeNull()
        ->and(PresentedQuote::find($presentedQuoteB->id))->not->toBeNull();
});

it('refuses a presented quote that belongs to a different request', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $otherRequest = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $foreignResponse = VendorResponse::factory()->create(['part_request_id' => $otherRequest->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    $foreignPresentedQuote = app(PresentQuoteAction::class)->execute($otherRequest, $foreignResponse);

    $attempt = fn () => app(SelectQuoteAction::class)->execute($request, $foreignPresentedQuote);

    expect($attempt)->toThrow(SelectQuoteNotAllowedException::class);
    expect($request->fresh()->selected_response_id)->toBeNull();
});

it('refuses to select once the request has already been paid for', function (RequestStatus $status) {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    $request = $request->fresh();
    $request->update(['status' => $status]);

    $attempt = fn () => app(SelectQuoteAction::class)->execute($request, $presentedQuote);

    expect($attempt)->toThrow(SelectQuoteNotAllowedException::class);
    expect($request->fresh()->selected_response_id)->toBeNull();
})->with([
    'paid' => RequestStatus::Paid,
    'ordered to vendor' => RequestStatus::OrderedToVendor,
    'shipped' => RequestStatus::Shipped,
    'received' => RequestStatus::Received,
]);

it('rolls back the whole selection if the transaction fails', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    PartRequest::updating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $attempt = fn () => app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    expect($attempt)->toThrow(RuntimeException::class);
    expect($request->fresh()->selected_response_id)->toBeNull();
});

it('copies is_free onto the part_request when selecting a free presented quote (CLAUDE.md §14 Phase 4 slice 5)', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 10,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);

    $result = app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    expect($result->is_free)->toBeTrue()
        ->and($result->buyer_price)->toBe(0)
        ->and($result->shipping_fee)->toBe(0);
});
