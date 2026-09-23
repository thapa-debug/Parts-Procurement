<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * The mirror of ConfirmStripePaymentAction (CLAUDE.md §14 Phase 4 stripe
 * integration) for Stripe's `payment_intent.payment_failed` event -- also
 * only ever called from StripeWebhookController, after signature
 * verification.
 *
 * Deliberately refuses to downgrade an already-confirmed payment: money
 * that has actually been captured must never be silently marked failed by
 * a stray or out-of-order webhook delivery (CLAUDE.md §10 -- never destroy
 * payment data). A PaymentIntent can be retried with a different payment
 * method after a decline, so a `payment_failed` event for a payment that's
 * still pending is the normal, expected case -- not a bug.
 */
class FailStripePaymentAction
{
    public function execute(string $paymentIntentId): void
    {
        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->where('gateway_reference', $paymentIntentId)
            ->first();

        if ($payment === null) {
            Log::channel('payments')->warning('Stripe webhook: payment_intent.payment_failed for an unknown PaymentIntent', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        if ($payment->status === PaymentStatus::Confirmed) {
            Log::channel('payments')->warning('Stripe webhook: payment_intent.payment_failed for an already-confirmed payment, ignored', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        $payment->update(['status' => PaymentStatus::Failed]);

        Log::channel('payments')->warning('Stripe webhook: payment failed', [
            'payment_id' => $payment->id,
            'part_request_id' => $payment->part_request_id,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }
}
