<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;

uses(RefreshDatabase::class);

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('confirms the stuck requests Stripe says were paid, and leaves the unpaid ones alone', function () {
    [$paid, $paidPayment] = awaitingStripePayment('pi_paid');
    [$unpaid, $unpaidPayment] = awaitingStripePayment('pi_unpaid');
    fakeStripeRoutes([
        'get /v1/payment_intents/pi_paid' => stripeIntent('pi_paid', 'succeeded'),
        'get /v1/payment_intents/pi_unpaid' => stripeIntent('pi_unpaid', 'requires_payment_method'),
    ]);

    $this->artisan('payments:reconcile')
        ->expectsOutputToContain("{$paid->request_code}: confirmed")
        ->expectsOutputToContain("{$unpaid->request_code}: not_paid")
        ->assertSuccessful();

    expect($paid->fresh()->status)->toBe(RequestStatus::Paid)
        ->and($paidPayment->refresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($unpaid->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($unpaidPayment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('is safe to run again -- already-reconciled requests are not touched', function () {
    [$paid] = awaitingStripePayment('pi_paid');
    fakeStripeRoutes(['get /v1/payment_intents/pi_paid' => stripeIntent('pi_paid', 'succeeded')]);
    $this->artisan('payments:reconcile')->assertSuccessful();

    $stripe = fakeStripeRoutes([]); // any Stripe call would throw

    $this->artisan('payments:reconcile')
        ->expectsOutputToContain('Checked 0 awaiting-payment request(s).')
        ->assertSuccessful();
    expect($stripe->calls)->toBe([]);
});

it('--dry-run reports what Stripe says without confirming anything', function () {
    [$paid, $payment] = awaitingStripePayment('pi_paid');
    fakeStripeRoutes(['get /v1/payment_intents/pi_paid' => stripeIntent('pi_paid', 'succeeded')]);

    $this->artisan('payments:reconcile', ['--dry-run' => true])
        ->expectsOutputToContain("{$paid->request_code}: succeeded")
        ->expectsOutputToContain('[dry run]')
        ->assertSuccessful();

    expect($paid->fresh()->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('fails loudly when Stripe took a different amount than we recorded', function () {
    [$request] = awaitingStripePayment('pi_odd');
    fakeStripeRoutes(['get /v1/payment_intents/pi_odd' => stripeIntent('pi_odd', 'succeeded', 61_000)]);

    $this->artisan('payments:reconcile')
        ->expectsOutputToContain("{$request->request_code}: amount_mismatch")
        ->assertFailed();

    expect($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

it('is scheduled every 15 minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'payments:reconcile'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
