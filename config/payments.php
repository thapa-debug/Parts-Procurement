<?php

use App\Payments\StripePaymentGateway;
use App\Payments\StubPaymentGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Active payment gateway
    |--------------------------------------------------------------------------
    |
    | Which entry under "gateways" below gets bound to the PaymentGateway
    | interface. Deliberately has NO fallback here -- PaymentServiceProvider
    | falls back to "stub" outside production only, and refuses to boot at
    | all with an unset or "stub" gateway in production (CLAUDE.md §14 Phase
    | 4 slice 1: the stub gateway can never become the production default).
    |
    */

    'gateway' => env('PAYMENT_GATEWAY'),

    /*
    |--------------------------------------------------------------------------
    | Registered gateways
    |--------------------------------------------------------------------------
    |
    | Maps a gateway name to the PaymentGateway implementation it resolves
    | to -- calling code (CheckoutAction) never changes, it only ever depends
    | on the PaymentGateway interface. "stripe" is the production gateway;
    | "stub" remains for local dev and the test suite (never bindable in
    | production -- see PaymentServiceProvider).
    |
    */

    'gateways' => [
        'stub' => StubPaymentGateway::class,
        'stripe' => StripePaymentGateway::class,
    ],

];
