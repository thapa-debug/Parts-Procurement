<?php

use App\Actions\CancelCheckoutAction;
use App\Actions\CheckoutAction;
use App\Actions\PresentQuoteAction;
use App\Actions\ReconcileStripePaymentAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutCancelNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\ShippingWeightBracket;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;
use App\Services\DhlRateCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function cancelCheckout(PartRequest $request): PartRequest
{
    return app(CancelCheckoutAction::class)->execute($request->fresh());
}

// --- cancels only what can't take money, and only once Stripe confirms it -------------

it('cancels an unpaid intent at Stripe, keeps the payment row as failed, and takes the request back to quoted', function () {
    [$request, $payment] = awaitingStripePayment();
    $stripe = fakeStripeRoutes([
        'get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'requires_payment_method'),
        'post /v1/payment_intents/pi_stuck/cancel' => stripeIntent('pi_stuck', 'canceled'),
    ]);

    $result = cancelCheckout($request);

    expect($result->status)->toBe(RequestStatus::Quoted)
        ->and($stripe->calls)->toBe(['get /v1/payment_intents/pi_stuck', 'post /v1/payment_intents/pi_stuck/cancel'])
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->raw_response)->toMatchArray(['status' => 'canceled', 'canceled_by' => 'buyer'])
        ->and(Payment::where('part_request_id', $request->id)->count())->toBe(1);
});

it('REFUSES to cancel an intent Stripe says succeeded -- the buyer has paid; reconcile it instead', function () {
    [$request, $payment] = awaitingStripePayment();
    // No cancel route: any cancel call would throw.
    $stripe = fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded')]);

    expect(fn () => cancelCheckout($request))
        ->toThrow(fn (CheckoutCancelNotAllowedException $e) => expect($e->alreadyPaid)->toBeTrue());

    expect($stripe->calls)->toBe(['get /v1/payment_intents/pi_stuck'])
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);

    // What the caller does next: reconcile -- the request becomes paid.
    expect(app(ReconcileStripePaymentAction::class)->execute($request->fresh()))->toBe(PaymentReconciliation::Confirmed)
        ->and($request->fresh()->status)->toBe(RequestStatus::Paid);
});

it('refuses to cancel a payment in flight', function (string $status) {
    [$request, $payment] = awaitingStripePayment();
    $stripe = fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', $status)]);

    expect(fn () => cancelCheckout($request))
        ->toThrow(fn (CheckoutCancelNotAllowedException $e) => expect($e->inProgress)->toBeTrue());

    expect($stripe->calls)->toBe(['get /v1/payment_intents/pi_stuck'])
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
})->with(['processing', 'requires_capture']);

it('changes nothing when Stripe rejects the cancel', function () {
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes([
        'get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'requires_payment_method'),
        'post /v1/payment_intents/pi_stuck/cancel' => ['__status' => 400, 'error' => ['type' => 'invalid_request_error', 'code' => 'payment_intent_unexpected_state', 'message' => 'no']],
    ]);

    expect(fn () => cancelCheckout($request))->toThrow(CheckoutCancelNotAllowedException::class);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

it('changes nothing when Stripe reports the intent still not canceled', function () {
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes([
        'get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'requires_payment_method'),
        'post /v1/payment_intents/pi_stuck/cancel' => stripeIntent('pi_stuck', 'requires_payment_method'),
    ]);

    expect(fn () => cancelCheckout($request))->toThrow(CheckoutCancelNotAllowedException::class);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

it('does not call cancel on an intent that is already canceled', function () {
    [$request] = awaitingStripePayment();
    $stripe = fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'canceled')]);

    expect(cancelCheckout($request)->status)->toBe(RequestStatus::Quoted)
        ->and($stripe->calls)->toBe(['get /v1/payment_intents/pi_stuck']);
});

it('never touches Stripe for a request that is not awaiting payment', function (RequestStatus $status) {
    [$request] = awaitingStripePayment();
    $request->update(['status' => $status]);
    $stripe = fakeStripeRoutes([]);

    expect(fn () => cancelCheckout($request))->toThrow(CheckoutCancelNotAllowedException::class);
    expect($stripe->calls)->toBe([]);
})->with([RequestStatus::Quoted, RequestStatus::Paid]);

// --- starting over: a clean re-checkout --------------------------------------------------

it('lets the buyer start over cleanly: re-select a quote, change shipping, and pay on ONE new intent for the new total', function () {
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $cheap = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => VendorProfile::factory()->create()->id, 'cost_price' => 45_000, 'weight_kg' => 12]);
    $dear = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => VendorProfile::factory()->create()->id, 'cost_price' => 60_000, 'weight_kg' => 12]);
    $cheapQuote = app(PresentQuoteAction::class)->execute($request, $cheap);
    $dearQuote = app(PresentQuoteAction::class)->execute($request->fresh(), $dear);
    app(SelectQuoteAction::class)->execute($request->fresh(), $cheapQuote);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $useStripe = function (RoutedFakeStripeHttpClient $http) {
        app()->instance(PaymentGateway::class, new StripePaymentGateway(app(StripeClient::class)));
    };

    // First checkout: cheap quote, Regular -> 54,000 + 8,000.
    $useStripe(fakeStripeRoutes(['post /v1/payment_intents' => stripeIntent('pi_first', 'requires_payment_method', 62_000)]));
    app(CheckoutAction::class)->execute($request->fresh(), $address);

    // Cancel it and start over.
    fakeStripeRoutes([
        'get /v1/payment_intents/pi_first' => stripeIntent('pi_first', 'requires_payment_method', 62_000),
        'post /v1/payment_intents/pi_first/cancel' => stripeIntent('pi_first', 'canceled', 62_000),
    ]);
    cancelCheckout($request);

    // Choose the other quote and DHL: 72,000 + the DHL fee.
    app(SelectQuoteAction::class)->execute($request->fresh(), $dearQuote);
    $newTotal = 72_000 + DhlRateCalculator::DUMMY_FEE;
    $stripe = fakeStripeRoutes(['post /v1/payment_intents' => stripeIntent('pi_second', 'requires_payment_method', $newTotal)]);
    $useStripe($stripe);
    $result = app(CheckoutAction::class)->execute($request->fresh(), $address, ShippingMethod::Dhl, $newTotal);

    expect($result->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($result->buyer_price)->toBe(72_000)
        ->and($result->shipping_method)->toBe(ShippingMethod::Dhl)
        ->and($stripe->calls)->toBe(['post /v1/payment_intents']);

    $payments = Payment::where('part_request_id', $request->id)->orderBy('id')->get();
    expect($payments->pluck('status', 'gateway_reference')->all())
        ->toBe(['pi_first' => PaymentStatus::Failed, 'pi_second' => PaymentStatus::Pending])
        ->and($payments[1]->amount)->toBe($newTotal);
});
