<?php

use App\Actions\ConfirmStripePaymentAction;
use App\Actions\ReconcileStripePaymentAction;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedAdminNotification;
use App\Notifications\PaymentConfirmedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Stripe\ApiRequestor;

uses(RefreshDatabase::class);

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function reconcile(PartRequest $request): PaymentReconciliation
{
    return app(ReconcileStripePaymentAction::class)->execute($request->fresh());
}

// --- a succeeded intent: confirmed through the webhook's own path, exactly once ------

it('confirms a payment Stripe says succeeded: payment confirmed, request paid, buyer and admin notified once', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [$request, $payment] = awaitingStripePayment();
    $stripe = fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded')]);

    expect(reconcile($request))->toBe(PaymentReconciliation::Confirmed);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($request->fresh()->status)->toBe(RequestStatus::Paid)
        ->and($stripe->calls)->toBe(['get /v1/payment_intents/pi_stuck']);

    Notification::assertSentToTimes($request->buyer->user, PaymentConfirmedNotification::class, 1);
    Notification::assertSentToTimes($admin, PaymentConfirmedAdminNotification::class, 1);
});

it('records paid_at as when Stripe actually charged the card, not when reconcile noticed', function () {
    // Paid on 12 Oct; the webhook never arrived; reconciled a month later.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 14:48:00', 'Asia/Tokyo'));
    [$request, $payment] = awaitingStripePayment();
    $chargedAt = CarbonImmutable::parse('2026-10-12 15:11:51', 'Asia/Tokyo');
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded') + [
        'latest_charge' => ['id' => 'ch_stuck', 'object' => 'charge', 'status' => 'succeeded', 'created' => $chargedAt->getTimestamp()],
    ]]);

    reconcile($request);

    expect($payment->refresh()->paid_at->format('Y-m-d H:i:s'))->toBe('2026-10-12 15:11:51');
});

it('reconciling again does nothing -- no second confirmation, no second notification, no Stripe call', function () {
    Notification::fake();
    [$request] = awaitingStripePayment();
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded')]);
    reconcile($request);

    $stripe = fakeStripeRoutes([]); // any Stripe call would now throw

    expect(reconcile($request))->toBe(PaymentReconciliation::NothingToReconcile)
        ->and($stripe->calls)->toBe([]);
    Notification::assertSentToTimes($request->buyer->user, PaymentConfirmedNotification::class, 1);
});

it('never double-confirms when the webhook lands mid-reconcile -- confirmed once, notified once', function () {
    Notification::fake();
    [$request, $payment] = awaitingStripePayment();
    // The webhook confirms the payment while reconcile is talking to Stripe.
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => function () {
        app(ConfirmStripePaymentAction::class)->execute('pi_stuck');

        return stripeIntent('pi_stuck', 'succeeded');
    }]);

    expect(reconcile($request))->toBe(PaymentReconciliation::AlreadyConfirmed);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($request->fresh()->status)->toBe(RequestStatus::Paid);
    Notification::assertSentToTimes($request->buyer->user, PaymentConfirmedNotification::class, 1);
});

it('leaves an already webhook-confirmed request alone', function () {
    [$request] = awaitingStripePayment();
    app(ConfirmStripePaymentAction::class)->execute('pi_stuck');
    $stripe = fakeStripeRoutes([]);

    expect(reconcile($request))->toBe(PaymentReconciliation::NothingToReconcile)
        ->and($stripe->calls)->toBe([]);
});

it('confirms a payment whose earlier card was declined but which then succeeded on the same intent', function () {
    [$request, $payment] = awaitingStripePayment(paymentStatus: PaymentStatus::Failed);
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded')]);

    expect(reconcile($request))->toBe(PaymentReconciliation::Confirmed)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Confirmed);
});

it('reconciles only the request\'s current payment, never an older canceled one', function () {
    [$request, $old] = awaitingStripePayment('pi_old', PaymentStatus::Failed);
    $current = Payment::factory()->create([
        'part_request_id' => $request->id, 'amount' => 62_000, 'currency' => 'JPY',
        'gateway' => 'stripe', 'gateway_reference' => 'pi_current', 'status' => PaymentStatus::Pending,
    ]);
    $stripe = fakeStripeRoutes(['get /v1/payment_intents/pi_current' => stripeIntent('pi_current', 'succeeded')]);

    expect(reconcile($request))->toBe(PaymentReconciliation::Confirmed)
        ->and($stripe->calls)->toBe(['get /v1/payment_intents/pi_current'])
        ->and($current->refresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($old->refresh()->status)->toBe(PaymentStatus::Failed);
});

// --- anything else: reported, never changed -----------------------------------------

it('reports a genuinely in-flight payment as processing and changes nothing', function (string $status) {
    Notification::fake();
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', $status)]);

    expect(reconcile($request))->toBe(PaymentReconciliation::Processing)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
    Notification::assertNothingSent();
})->with(['processing', 'requires_capture']);

it('reports an unpaid intent as not paid and changes nothing', function (string $status) {
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', $status)]);

    expect(reconcile($request))->toBe(PaymentReconciliation::NotPaid)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
})->with(['requires_payment_method', 'requires_confirmation', 'requires_action', 'canceled']);

it('never confirms a succeeded intent whose amount or currency differs from our record', function (int $amount, string $currency) {
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded', $amount, $currency)]);

    expect(reconcile($request))->toBe(PaymentReconciliation::AmountMismatch)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
})->with([
    'different amount' => [61_000, 'jpy'],
    'different currency' => [62_000, 'usd'],
]);

it('changes nothing when Stripe cannot be reached', function () {
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => ['__status' => 500, 'error' => ['type' => 'api_error', 'message' => 'down']]]);

    expect(reconcile($request))->toBe(PaymentReconciliation::StripeUnavailable)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('never asks Stripe about a request that is not awaiting payment', function (RequestStatus $status) {
    [$request] = awaitingStripePayment();
    $request->update(['status' => $status]);
    $stripe = fakeStripeRoutes([]);

    expect(reconcile($request))->toBe(PaymentReconciliation::NothingToReconcile)
        ->and($stripe->calls)->toBe([]);
})->with([RequestStatus::Quoted, RequestStatus::Paid]);

it('inspect() reports a succeeded intent without confirming it', function () {
    [$request, $payment] = awaitingStripePayment();
    fakeStripeRoutes(['get /v1/payment_intents/pi_stuck' => stripeIntent('pi_stuck', 'succeeded')]);

    expect(app(ReconcileStripePaymentAction::class)->inspect($request))->toBe(PaymentReconciliation::Succeeded)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});
