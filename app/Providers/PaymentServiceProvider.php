<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Binds the PaymentGateway interface to whichever gateway
 * config/payments.php selects (CLAUDE.md §14 Phase 4 slice 1). The one hard
 * rule enforced here, not left to config alone: the stub gateway can never
 * run in production, whether that's because PAYMENT_GATEWAY was left unset
 * or was explicitly (mis)configured as "stub".
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One client per request is plenty -- nothing here is
        // request-scoped state that would leak between requests. Tests
        // never mock this binding directly (StripeClient's real services
        // like ->paymentIntents are lazily-constructed magic properties,
        // awkward to mock cleanly) -- instead they swap Stripe's own HTTP
        // transport (\Stripe\ApiRequestor::setHttpClient()) so the real
        // SDK code runs against a canned response, never a real network
        // call (CLAUDE.md §9: tests must not hit real Stripe).
        //
        // `?: []` matters: StripeClient's constructor requires a string or
        // an array, and only an *array* config (defaulting api_key to
        // null internally) is tolerated when no key is configured -- a
        // bare `null` argument fails that type check before api_key is
        // ever inspected. An unset STRIPE_SECRET resolves to '' rather
        // than null whenever a literal (empty) STRIPE_SECRET= line exists
        // in the loaded .env -- exactly what CI's own `cp .env.example
        // .env` step produces, with no real Stripe credentials configured.
        $this->app->singleton(StripeClient::class, fn () => new StripeClient(
            config('services.stripe.secret') ?: []
        ));

        $this->app->bind(PaymentGateway::class, function ($app) {
            $gateway = config('payments.gateway');

            if ($gateway === null) {
                if ($app->isProduction()) {
                    throw new RuntimeException(
                        'PAYMENT_GATEWAY must be explicitly configured in production -- it never defaults to the stub gateway.'
                    );
                }

                $gateway = 'stub';
            }

            if ($gateway === 'stub' && $app->isProduction()) {
                throw new RuntimeException('The stub payment gateway can never run in production.');
            }

            $class = config("payments.gateways.{$gateway}");

            if ($class === null) {
                throw new InvalidArgumentException("Unknown payment gateway [{$gateway}]. Check config/payments.php.");
            }

            return $app->make($class);
        });
    }
}
