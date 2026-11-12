<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The ONLY path that ever marks a Stripe payment confirmed (CLAUDE.md §6.3,
 * §14 Phase 4 stripe integration). A payment is confirmed only on evidence
 * from Stripe itself, never the browser -- so this is called from exactly
 * two places, both server-side:
 * - StripeWebhookController, after verifying the webhook's signature
 *   (SOURCE_WEBHOOK, the primary path);
 * - ReconcileStripePaymentAction, after retrieving the PaymentIntent from
 *   Stripe's API with our secret key and seeing it `succeeded`
 *   (SOURCE_RECONCILE, the backstop for a webhook that never arrived).
 * A redirect landing back on our own site proves nothing (a buyer could
 * navigate there without paying), so nothing the browser does reaches here.
 *
 * Confirms exactly once. Stripe may redeliver a webhook (at-least-once
 * delivery), and a webhook may race a reconcile, so the "already
 * confirmed?" check runs on the payment row locked FOR UPDATE inside the
 * transaction: a second caller waits for the first to commit, then sees
 * `confirmed` and does nothing. Also handles a PaymentIntent that failed
 * once and later succeeded on a retried card (same Stripe object) -- so the
 * guard is "not already confirmed", not "must currently be pending".
 *
 * Also the only thing that moves a Stripe-paid request from
 * `awaiting_payment` to `paid`, in the same transaction as the payment --
 * the request never reads `paid` before the money is confirmed. A request
 * already past `awaiting_payment` is never moved.
 *
 * Notified exactly once: the buyer/admin payment-confirmed notifications
 * (SendPaymentConfirmedNotificationsAction) fire only when THIS call did
 * the confirming -- never for a redelivery or the losing side of a race.
 */
class ConfirmStripePaymentAction
{
    public const SOURCE_WEBHOOK = 'webhook';

    public const SOURCE_RECONCILE = 'reconcile';

    public function __construct(
        private readonly SendPaymentConfirmedNotificationsAction $sendPaymentConfirmedNotifications,
    ) {}

    /**
     * $paidAt: when Stripe actually took the money. The webhook arrives
     * within seconds, so it defaults to now(); a reconcile can run weeks
     * after the charge, so it passes the charge's own time -- paid_at
     * decides which reporting period the order's revenue falls in.
     *
     * @return bool true only when this call confirmed the payment (false if
     *              it was unknown or already confirmed)
     */
    public function execute(string $paymentIntentId, string $source = self::SOURCE_WEBHOOK, ?CarbonInterface $paidAt = null): bool
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
                'source' => $source,
            ]);

            return false;
        }

        $confirmedNow = DB::transaction(function () use ($payment, $paymentIntentId, $source, $paidAt) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Confirmed) {
                Log::channel('payments')->info('Stripe webhook: payment_intent.succeeded for an already-confirmed payment, ignored', [
                    'payment_id' => $locked->id,
                    'payment_intent_id' => $paymentIntentId,
                    'source' => $source,
                ]);

                return false;
            }

            $locked->update([
                'status' => PaymentStatus::Confirmed,
                'paid_at' => $paidAt ?? now(),
            ]);

            $partRequest = $locked->partRequest;

            if ($partRequest?->status === RequestStatus::AwaitingPayment) {
                $partRequest->update(['status' => RequestStatus::Paid]);
            } else {
                Log::channel('payments')->warning('Stripe webhook: payment confirmed for a request not awaiting payment, status left unchanged', [
                    'payment_id' => $locked->id,
                    'part_request_id' => $locked->part_request_id,
                    'request_status' => $partRequest?->status?->value,
                    'source' => $source,
                ]);
            }

            Log::channel('payments')->info('Stripe webhook: payment confirmed', [
                'payment_id' => $locked->id,
                'part_request_id' => $locked->part_request_id,
                'payment_intent_id' => $paymentIntentId,
                'source' => $source,
            ]);

            return true;
        });

        if ($confirmedNow) {
            $this->sendPaymentConfirmedNotifications->execute($payment->fresh());
        }

        return $confirmedNow;
    }
}
