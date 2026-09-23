<?php

use App\Actions\FailStripePaymentAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a pending stripe payment failed', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_123',
        'status' => PaymentStatus::Pending,
    ]);

    app(FailStripePaymentAction::class)->execute('pi_test_123');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
});

it('never downgrades an already-confirmed payment -- captured money is never marked failed by a stray webhook', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_456',
        'status' => PaymentStatus::Confirmed,
        'paid_at' => now(),
    ]);

    app(FailStripePaymentAction::class)->execute('pi_test_456');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed);
});

it('does nothing, and does not throw, for a PaymentIntent that does not match any payment', function () {
    $attempt = fn () => app(FailStripePaymentAction::class)->execute('pi_unknown_intent');

    expect($attempt)->not->toThrow(Throwable::class);
    expect(Payment::count())->toBe(0);
});
