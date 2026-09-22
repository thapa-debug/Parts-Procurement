<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The ONLY path that ever marks a Stripe payment confirmed (CLAUDE.md §6.3,
 * §14 Phase 4 stripe integration) -- called exclusively by
 * StripeWebhookController, after it has already verified the webhook's
 * signature. Never called from anything reachable by the browser: a
 * redirect landing back on our own site proves nothing (a buyer could
 * navigate there directly without ever paying), so this action only ever
 * runs off Stripe's own signed `payment_intent.succeeded` event.
 *
 * Idempotent by design -- Stripe explicitly may redeliver the same webhook
 * event more than once (network retries, at-least-once delivery), so this
 * must be safe to run twice for the same PaymentIntent. Also handles the
 * legitimate case of a PaymentIntent that failed once and later succeeded
 * on a retried payment method (the same Stripe object, confirmed again) --
 * so the guard is simply "not already confirmed", not "must currently be
 * pending".
 */
class ConfirmStripePaymentAction
{
    public function execute(string $paymentIntentId): void
    {
        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->where('gateway_reference', $paymentIntentId)
            ->first();

        if ($payment === null) {
            // Not necessarily a problem -- could be a webhook for a
            // PaymentIntent this app never created (a stale test event, a
            // different Stripe account/mode). Never throw: Stripe expects
            // a 2xx for anything we don't recognize, not a 500 that
            // triggers pointless retries.
            Log::channel('payments')->warning('Stripe webhook: payment_intent.succeeded for an unknown PaymentIntent', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        if ($payment->status === PaymentStatus::Confirmed) {
            Log::channel('payments')->info('Stripe webhook: payment_intent.succeeded for an already-confirmed payment, ignored', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        DB::transaction(function () use ($payment, $paymentIntentId) {
            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'paid_at' => now(),
            ]);

            Log::channel('payments')->info('Stripe webhook: payment confirmed', [
                'payment_id' => $payment->id,
                'part_request_id' => $payment->part_request_id,
                'payment_intent_id' => $paymentIntentId,
            ]);
        });
    }
}
