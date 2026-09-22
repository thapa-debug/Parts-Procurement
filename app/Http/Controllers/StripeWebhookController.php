<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmStripePaymentAction;
use App\Actions\FailStripePaymentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\PaymentIntent;
use Stripe\Webhook;

/**
 * Receives Stripe's webhook POSTs (CLAUDE.md §14 Phase 4 stripe
 * integration) -- the ONLY authoritative source for "is this payment
 * actually confirmed" (§6.3, money-critical). Deliberately thin (CLAUDE.md
 * §8): verify the signature, translate the event into a call to one of two
 * single-purpose actions, nothing else. All the actual state-mutation
 * logic lives in ConfirmStripePaymentAction/FailStripePaymentAction, which
 * are unreachable from anywhere else a browser could reach.
 *
 * No CSRF token (bootstrap/app.php excludes this route -- Stripe's server
 * has no session, no token to send) and no auth middleware (Stripe isn't
 * one of our users). The signature check below is what stands in for both:
 * a POST that doesn't carry a valid signature for our own webhook secret
 * is rejected outright, before either action class ever runs.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature') ?? '',
                $secret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            // Never process a payload we can't verify -- this is the one
            // line standing between "any POST from anywhere" and "money
            // gets marked confirmed". A malformed payload
            // (UnexpectedValueException, from constructEvent's own JSON
            // decode) is rejected the same way as a bad signature: neither
            // is something a real Stripe delivery would ever produce.
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        match ($event->type) {
            'payment_intent.succeeded' => app(ConfirmStripePaymentAction::class)->execute(
                $this->paymentIntentId($event)
            ),
            'payment_intent.payment_failed' => app(FailStripePaymentAction::class)->execute(
                $this->paymentIntentId($event)
            ),
            // Every other event type is acknowledged and ignored --
            // Stripe recommends responding quickly to anything you don't
            // handle rather than treating it as an error.
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function paymentIntentId(Event $event): string
    {
        /** @var PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;

        return $paymentIntent->id;
    }
}
