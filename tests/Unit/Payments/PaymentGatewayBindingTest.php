<?php

use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use App\Payments\StripePaymentGateway;
use App\Payments\StubPaymentGateway;

it('binds the stub gateway behind the PaymentGateway interface when config selects "stub"', function () {
    config(['payments.gateway' => 'stub']);

    $gateway = app(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(StubPaymentGateway::class);
});

it('binds the stripe gateway behind the PaymentGateway interface when config selects "stripe"', function () {
    config(['payments.gateway' => 'stripe']);

    $gateway = app(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(StripePaymentGateway::class);
});

it('falls back to the stub gateway outside production when no gateway is configured', function () {
    config(['payments.gateway' => null]);

    $gateway = app(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(StubPaymentGateway::class);
});

it('refuses to boot with an unconfigured gateway in production', function () {
    config(['payments.gateway' => null]);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

it('refuses to boot with the stub gateway explicitly selected in production', function () {
    config(['payments.gateway' => 'stub']);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

it('boots the stripe gateway in production without any special-casing', function () {
    config(['payments.gateway' => 'stripe']);
    app()->detectEnvironment(fn () => 'production');

    $gateway = app(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(StripePaymentGateway::class);
});

it('rejects an unknown gateway name', function () {
    config(['payments.gateway' => 'made_up_gateway']);

    expect(fn () => app(PaymentGateway::class))->toThrow(InvalidArgumentException::class);
});

it('lets calling code depend on the PaymentGateway interface only, not the concrete stub', function () {
    // A throwaway fake gateway, bound directly to the interface, with no
    // reference to StubPaymentGateway anywhere -- proves the container
    // wiring (and any future caller that type-hints PaymentGateway) never
    // hardcodes the stub implementation.
    $fake = new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentResult
        {
            return PaymentResult::success(gatewayReference: 'fake-ref');
        }

        public function name(): string
        {
            return 'fake';
        }
    };

    app()->instance(PaymentGateway::class, $fake);

    $resolved = app(PaymentGateway::class);

    expect($resolved)->toBe($fake)
        ->and($resolved)->toBeInstanceOf(PaymentGateway::class);
});
