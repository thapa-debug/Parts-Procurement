<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentResult;
use App\Payments\StubPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assumes success, marking the payment confirmed with a gateway reference', function () {
    $payment = Payment::factory()->create(['gateway' => 'stub']);

    $result = (new StubPaymentGateway)->charge($payment);

    expect($result)->toBeInstanceOf(PaymentResult::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->gatewayReference)->not->toBeNull();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->gateway)->toBe('stub')
        ->and($payment->gateway_reference)->toBe($result->gatewayReference)
        ->and($payment->paid_at)->not->toBeNull();
});

it('records the payment as still pending until charge() is called', function () {
    $payment = Payment::factory()->create();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->paid_at)->toBeNull();
});
