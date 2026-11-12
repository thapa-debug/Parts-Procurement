<?php

use App\Actions\BroadcastRequestAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SetUrlPriceAction;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\UrlSourcingNotAllowedException;
use App\Models\PartRequest;
use App\Models\Setting;
use App\Models\ShippingWeightBracket;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Notifications\UrlQuotePricedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 2]);
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 40_000, 'order' => 3]);
});

function newUrlRequest(array $attributes = []): PartRequest
{
    return PartRequest::factory()->create([
        'status' => RequestStatus::New,
        'reference_url' => 'https://example.com/listing/123',
        ...$attributes,
    ]);
}

// --- pricing: the same PricingService rule as a vendor quote (§6.1) -------

it('prices via PricingService and snapshots cost, rate, min fee and buyer price -- above the floor, the percentage wins', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);

    expect($request->cost_price)->toBe(45_000)
        ->and($request->applied_rate)->toBe(20)
        ->and($request->applied_min_fee)->toBe(2000)
        ->and($request->buyer_price)->toBe(54_000); // 45,000 + 9,000
});

it('applies the minimum-fee floor on a cheap part', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 3_000, weightKg: 1);

    // 20% of 3,000 = 600 < 2,000 floor.
    expect($request->buyer_price)->toBe(5_000)
        ->and($request->applied_min_fee)->toBe(2000);
});

it('uses whatever rate and floor are configured at pricing time', function () {
    Setting::set('margin_rate', 35, 'integer');
    Setting::set('margin_min_fee', 5000, 'integer');

    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 100_000, weightKg: 12);

    expect($request->applied_rate)->toBe(35)
        ->and($request->applied_min_fee)->toBe(5000)
        ->and($request->buyer_price)->toBe(135_000);
});

it('never drifts: a later margin change leaves the snapshotted price alone (§6.2)', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);

    Setting::set('margin_rate', 50, 'integer');
    Setting::set('margin_min_fee', 9000, 'integer');

    expect($request->fresh()->buyer_price)->toBe(54_000)
        ->and($request->fresh()->applied_rate)->toBe(20);
});

// --- status, flag, and shipping ---------------------------------------------

it('flags the request URL-sourced and moves it from new to quoted -- no new status', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);

    expect($request->is_url_sourced)->toBeTrue()
        ->and($request->status)->toBe(RequestStatus::Quoted)
        ->and($request->hasPricedQuote())->toBeTrue()
        ->and($request->selected_response_id)->toBeNull();
});

it('skips vendors entirely: no 打診, no invited vendors, no responses', function () {
    VendorProfile::factory()->count(3)->create();

    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);

    expect($request->vendors()->count())->toBe(0)
        ->and($request->vendorResponses()->count())->toBe(0)
        ->and($request->presentedQuotes()->count())->toBe(0)
        ->and($request->confirmed_vendor_id)->toBeNull();
});

it('calculates shipping from the entered weight with the same brackets as a vendor quote, as the Regular default', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);

    expect((float) $request->url_weight_kg)->toBe(12.0)
        ->and($request->url_shipping_fee)->toBe(8_000)
        ->and($request->shipping_fee)->toBe(8_000)
        ->and($request->shipping_method)->toBe(ShippingMethod::Standard)
        ->and($request->url_shipping_fee_overridden)->toBeFalse()
        ->and($request->url_shipping_fee_override_reason)->toBeNull();
});

it('reaches the catch-all bracket for a heavy part', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 200_000, weightKg: 180);

    expect($request->url_shipping_fee)->toBe(40_000);
});

it('lets the admin override the shipping fee with a reason, stored and audited', function () {
    $request = app(SetUrlPriceAction::class)->execute(
        newUrlRequest(), urlCost: 45_000, weightKg: 12,
        shippingFeeOverride: 12_500, shippingFeeOverrideReason: 'Oversized box',
    );

    expect($request->url_shipping_fee)->toBe(12_500)
        ->and($request->shipping_fee)->toBe(12_500)
        ->and($request->url_shipping_fee_overridden)->toBeTrue()
        ->and($request->url_shipping_fee_override_reason)->toBe('Oversized box');

    $activity = Activity::query()->where('event', 'url_priced')->sole();
    expect($activity->subject_id)->toBe($request->id)
        ->and($activity->properties['cost_price'])->toBe(45_000)
        ->and($activity->properties['buyer_price'])->toBe(54_000)
        ->and($activity->properties['shipping_fee_override_reason'])->toBe('Oversized box');
});

// --- refusals: nothing is written --------------------------------------------

it('refuses an override without a reason', function (?string $reason) {
    $request = newUrlRequest();

    expect(fn () => app(SetUrlPriceAction::class)->execute($request, 45_000, 12, 12_500, $reason))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->is_url_sourced)->toBeFalse()
        ->and($request->fresh()->status)->toBe(RequestStatus::New);
})->with([null, '', '   ']);

it('refuses a non-positive cost, weight, or a negative override', function (int $cost, float $weight, ?int $override) {
    $request = newUrlRequest();

    expect(fn () => app(SetUrlPriceAction::class)->execute($request, $cost, $weight, $override, 'reason'))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->buyer_price)->toBeNull();
})->with([
    'zero cost' => [0, 12, null],
    'negative cost' => [-1, 12, null],
    'zero weight' => [45_000, 0, null],
    'negative override' => [45_000, 12, -1],
]);

it('refuses a request with no reference URL', function (?string $url) {
    $request = newUrlRequest(['reference_url' => $url]);

    expect(fn () => app(SetUrlPriceAction::class)->execute($request, 45_000, 12))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->is_url_sourced)->toBeFalse();
})->with([null, '']);

it('refuses a request already sent to vendors -- one front half or the other, never both', function () {
    $request = newUrlRequest();
    $vendor = VendorProfile::factory()->create();
    app(BroadcastRequestAction::class)->execute($request, [$vendor->id]);

    expect(fn () => app(SetUrlPriceAction::class)->execute($request->fresh(), 45_000, 12))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->is_url_sourced)->toBeFalse()
        ->and($request->fresh()->status)->toBe(RequestStatus::VendorInquiry);
});

it('prices only once -- a second call is refused and the first price stands', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);

    // Even passing a stale copy that still reads `new`.
    $stale = PartRequest::find($request->id);
    $stale->status = RequestStatus::New;

    expect(fn () => app(SetUrlPriceAction::class)->execute($stale, 10_000, 1))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->buyer_price)->toBe(54_000);
});

it('never lets a vendor quote be presented on a URL-sourced request', function () {
    $request = app(SetUrlPriceAction::class)->execute(newUrlRequest(), urlCost: 45_000, weightKg: 12);
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'cost_price' => 30_000, 'weight_kg' => 5]);

    expect(fn () => app(PresentQuoteAction::class)->execute($request, $response))
        ->toThrow(UrlSourcingNotAllowedException::class);

    expect($request->fresh()->buyer_price)->toBe(54_000);
});

// --- buyer notification: the marked-up price only -----------------------------

it('notifies the buyer with the marked-up price only -- never the URL cost or margin', function () {
    Notification::fake();
    $request = newUrlRequest();
    $buyerUser = $request->buyer->user;

    app(SetUrlPriceAction::class)->execute($request, urlCost: 45_000, weightKg: 12, shippingFeeOverride: 9_999, shippingFeeOverrideReason: 'Internal reason');

    Notification::assertSentTo($buyerUser, UrlQuotePricedNotification::class, function (UrlQuotePricedNotification $notification) use ($buyerUser) {
        $payload = json_encode($notification->toArray($buyerUser));
        $mail = json_encode($notification->toMail($buyerUser)->toArray());

        return str_contains($payload, '54000')
            && ! str_contains($payload.$mail, '45000')
            && ! str_contains($payload.$mail, '45,000')
            && ! str_contains($payload.$mail, 'cost')
            && ! str_contains($payload.$mail, 'Internal reason')
            && ! str_contains($payload.$mail, 'example.com/listing');
    });
});

it('still prices the request when the buyer notification fails to send (§10)', function () {
    $request = newUrlRequest();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('mail transport unavailable'));

    $priced = app(SetUrlPriceAction::class)->execute($request, urlCost: 45_000, weightKg: 12);

    expect($priced->status)->toBe(RequestStatus::Quoted)
        ->and($request->fresh()->buyer_price)->toBe(54_000);
});
