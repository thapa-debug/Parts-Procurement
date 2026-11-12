<?php

use App\Actions\CheckoutAction;
use App\Actions\ConfirmOrderToVendorAction;
use App\Actions\MarkPurchasedFromUrlAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Actions\SetUrlPriceAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Exceptions\UrlSourcingNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\ShippingWeightBracket;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);
});

/** A URL-sourced request, priced and sitting at `quoted` -- not paid. */
function pricedUrlRequest(): PartRequest
{
    $request = PartRequest::factory()->create([
        'status' => RequestStatus::New,
        'reference_url' => 'https://example.com/listing/123',
    ]);

    return app(SetUrlPriceAction::class)->execute($request, urlCost: 45_000, weightKg: 12);
}

/** Priced, then paid through the real CheckoutAction (stub gateway). */
function paidUrlRequest(): PartRequest
{
    $request = pricedUrlRequest();
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    return app(CheckoutAction::class)->execute($request, $address);
}

it('marks a paid URL request purchased: ordered_to_vendor, with the reference, and no vendor', function () {
    $request = paidUrlRequest();

    $result = app(MarkPurchasedFromUrlAction::class)->execute($request, '  Order #A-778  ');

    expect($result->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($result->url_purchase_reference)->toBe('Order #A-778')
        ->and($result->confirmed_vendor_id)->toBeNull()
        ->and($result->receiving_address_id)->toBeNull();
});

it('treats the reference as optional', function (?string $reference) {
    $result = app(MarkPurchasedFromUrlAction::class)->execute(paidUrlRequest(), $reference);

    expect($result->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($result->url_purchase_reference)->toBeNull();
})->with([null, '', '   ']);

it('writes who purchased it to the activity log', function () {
    $request = paidUrlRequest();

    app(MarkPurchasedFromUrlAction::class)->execute($request, 'Order #A-778');

    $activity = Activity::query()->where('event', 'purchased_from_url')->sole();
    expect($activity->subject_id)->toBe($request->id)
        ->and($activity->properties['url_purchase_reference'])->toBe('Order #A-778');
});

// --- §6.3: pay before purchase -- the money-critical gate ---------------------

it('refuses to mark an unpaid (quoted) URL request purchased', function () {
    $request = pricedUrlRequest();

    expect(fn () => app(MarkPurchasedFromUrlAction::class)->execute($request, 'Order #A-778'))
        ->toThrow(PaymentNotConfirmedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Quoted)
        ->and($request->fresh()->url_purchase_reference)->toBeNull()
        ->and(Activity::query()->where('event', 'purchased_from_url')->exists())->toBeFalse();
});

it('refuses while the payment is only started (awaiting_payment, payment pending)', function () {
    $request = pricedUrlRequest();
    $request->update(['status' => RequestStatus::AwaitingPayment]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Pending, 'amount' => 62_000]);

    expect(fn () => app(MarkPurchasedFromUrlAction::class)->execute($request))
        ->toThrow(PaymentNotConfirmedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

it('refuses a request whose status says paid but has no confirmed payment record', function (PaymentStatus $paymentStatus) {
    $request = pricedUrlRequest();
    $request->update(['status' => RequestStatus::Paid]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => $paymentStatus, 'amount' => 62_000]);

    expect(fn () => app(MarkPurchasedFromUrlAction::class)->execute($request))
        ->toThrow(PaymentNotConfirmedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Paid);
})->with([PaymentStatus::Pending, PaymentStatus::Failed]);

it('cannot run twice -- a second call finds the request already ordered', function () {
    $request = paidUrlRequest();
    app(MarkPurchasedFromUrlAction::class)->execute($request, 'first');

    expect(fn () => app(MarkPurchasedFromUrlAction::class)->execute($request, 'second'))
        ->toThrow(PaymentNotConfirmedException::class);

    expect($request->fresh()->url_purchase_reference)->toBe('first');
});

// --- the two fulfilment paths never cross ---------------------------------------

it('refuses a vendor-flow request -- that one is confirmed to its vendor instead', function () {
    [$request] = paidRequestViaCheckoutForUrlTest();

    expect(fn () => app(MarkPurchasedFromUrlAction::class)->execute($request))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Paid);
});

it('refuses to confirm a URL-sourced request to a vendor -- there is none', function () {
    $request = paidUrlRequest();

    expect(fn () => app(ConfirmOrderToVendorAction::class)->execute($request, CompanyAddress::factory()->create()))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::Paid)
        ->and($request->fresh()->receiving_address_id)->toBeNull();
});

/**
 * A vendor-flow request paid through the real flow (present → select →
 * checkout), for the cross-path refusal above.
 *
 * @return array{0: PartRequest}
 */
function paidRequestViaCheckoutForUrlTest(): array
{
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);
    $quote = app(PresentQuoteAction::class)->execute($request, $response);
    app(SelectQuoteAction::class)->execute($request->fresh(), $quote);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    return [app(CheckoutAction::class)->execute($request->fresh(), $address)];
}
