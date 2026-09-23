<?php

namespace App\Payments;

use App\Models\Payment;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * The production payment gateway (CLAUDE.md §14 Phase 4 slice 1/stripe
 * integration) -- raw Stripe PaymentIntents via the stripe-php SDK, NOT
 * Cashier (these are one-off charges, never a subscription).
 *
 * charge() only ever CREATES the PaymentIntent -- it never confirms one,
 * and it never marks the Payment row confirmed itself. Card details are
 * collected and confirmed client-side (Stripe.js/Elements, a later UI
 * slice), which is the only PCI-compliant way to handle them; our server
 * never sees a raw card number. Deliberately un-confirmed here also keeps
 * this method genuinely synchronous and side-effect-light: "successful"
 * means "Stripe accepted the request to create a PaymentIntent", nothing
 * about whether money has moved.
 *
 * CRITICAL (CLAUDE.md §6.3, money-critical): the ONLY thing that is ever
 * allowed to mark a Stripe payment `confirmed` is ConfirmStripePaymentAction,
 * called exclusively from StripeWebhookController after verifying the
 * webhook's signature. Never the browser, never a redirect landing back on
 * our site, never this class. A client-side "it succeeded!" callback is
 * trivially fakeable (devtools, a tampered response, a replayed request);
 * a signature-verified webhook is not. This is exactly why
 * CheckoutAction's own gate (RequestStatus::Paid, set synchronously once
 * this method returns success) and the real payment gate
 * (ConfirmOrderToVendorAction, which separately requires a `confirmed`
 * Payment row) are allowed to disagree for a short window: a request can
 * legitimately read `paid` before its Payment row reads `confirmed` --
 * CheckoutAction is unchanged by this integration (the whole point of the
 * PaymentGateway abstraction, CLAUDE.md §14 Phase 4 slice 1), and the
 * admin-facing "can I confirm to the vendor yet" gate never opens on
 * `paid` status alone regardless.
 *
 * Amounts: CLAUDE.md §6.1/§7 already stores money as integer yen, no
 * decimals -- and JPY is one of Stripe's own zero-decimal currencies, so
 * `$payment->amount` is passed to Stripe completely as-is. Do NOT
 * multiply by 100 here the way you would for a decimal currency like USD;
 * that would overcharge by 100x.
 */
final class StripePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $client,
    ) {}

    public function charge(Payment $payment): PaymentResult
    {
        try {
            $intent = $this->client->paymentIntents->create(
                [
                    'amount' => $payment->amount,
                    'currency' => strtolower($payment->currency),
                    // Explicit, not automatic_payment_methods -- we only
                    // want card payments (no Link, no wallets). The
                    // frontend (checkout.blade.php's proceedToPayment())
                    // initializes Stripe Elements directly with THIS
                    // intent's own client secret
                    // (`stripe.elements({clientSecret})`), so Elements
                    // automatically inherits this restriction from the
                    // intent itself -- there's no separate frontend
                    // paymentMethodTypes setting to keep in sync any more
                    // (an earlier "deferred" Elements setup did need one,
                    // and a mismatch between the two was exactly what
                    // caused a live bug where the frontend ended up
                    // confirming a different intent than this one; see
                    // CONVENTIONS.md).
                    'payment_method_types' => ['card'],
                    'metadata' => [
                        'payment_id' => (string) $payment->id,
                        'part_request_id' => (string) $payment->part_request_id,
                    ],
                ],
                [
                    // Stable per Payment row -- a retried create() for the
                    // SAME row (e.g. a queue/transaction retry) returns the
                    // original PaymentIntent instead of creating a second
                    // one. The buyer-facing double-click case is separately
                    // guarded by the checkout button's own wire:loading
                    // disable; this is the API-call-level backstop.
                    'idempotency_key' => "checkout_payment_{$payment->id}_create",
                ]
            );
        } catch (ApiErrorException $e) {
            // Never log raw_response/full payloads here (CLAUDE.md §11) --
            // just enough to diagnose which request failed and why.
            return PaymentResult::failure(rawResponse: [
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);
        }

        $payment->update([
            'gateway_reference' => $intent->id,
            'raw_response' => [
                'status' => $intent->status,
            ],
        ]);

        return PaymentResult::success(gatewayReference: $intent->id, rawResponse: [
            'status' => $intent->status,
            // NOT persisted to payments.raw_response -- Stripe's own docs
            // for this field are explicit: "should not be stored, logged,
            // or exposed to anyone other than the customer." It only ever
            // travels this one hop, back to whichever caller needs it in
            // this same request (a future Stripe.js/Elements UI, to
            // mount and confirm the PaymentIntent client-side) -- never
            // written to a column, never logged.
            'client_secret' => $intent->client_secret,
        ]);
    }

    public function name(): string
    {
        return 'stripe';
    }
}
