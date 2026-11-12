<?php

namespace App\Actions;

use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\PartRequest;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * The backstop for a Stripe webhook that never arrived (CLAUDE.md §14
 * stripe integration). Asks Stripe's API -- server to server, with our
 * secret key, so the browser can't fake the answer -- what actually
 * happened to an awaiting_payment request's PaymentIntent:
 *
 * - succeeded (matching amount + currency) -> confirm it through
 *   ConfirmStripePaymentAction, the SAME single path the webhook uses:
 *   confirmed once, request moved to paid, notified once. If the webhook
 *   also arrives, or already did, nothing happens twice.
 * - processing / requires_capture -> genuinely in flight: report it,
 *   change nothing (the webhook or a later reconcile will confirm).
 * - anything else -> Stripe hasn't taken the money: report it, change
 *   nothing (the buyer can resume -- see ResumeStripePaymentAction).
 * - succeeded but for a different amount/currency -> NOT confirmed, logged
 *   as critical for a human: money was taken, but not what we recorded.
 *
 * Never cancels, creates or charges anything -- it only reads from Stripe
 * and, at most, records a confirmation Stripe already made.
 */
class ReconcileStripePaymentAction
{
    private const IN_FLIGHT = [PaymentIntent::STATUS_PROCESSING, PaymentIntent::STATUS_REQUIRES_CAPTURE];

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly ConfirmStripePaymentAction $confirmStripePayment,
    ) {}

    /**
     * Reconcile: confirm the payment if Stripe says it succeeded.
     */
    public function execute(PartRequest $partRequest): PaymentReconciliation
    {
        [$outcome, $paymentIntentId, $chargedAt] = $this->check($partRequest);

        if ($outcome !== PaymentReconciliation::Succeeded) {
            return $outcome;
        }

        // paid_at = when Stripe took the money, not when we noticed: a
        // reconcile can run weeks later, and paid_at decides which
        // reporting period the revenue falls in.
        return $this->confirmStripePayment->execute($paymentIntentId, ConfirmStripePaymentAction::SOURCE_RECONCILE, $chargedAt)
            ? PaymentReconciliation::Confirmed
            : PaymentReconciliation::AlreadyConfirmed;
    }

    /**
     * Read-only: what Stripe says, without confirming anything (Succeeded
     * instead of Confirmed). Used by `payments:reconcile --dry-run`.
     */
    public function inspect(PartRequest $partRequest): PaymentReconciliation
    {
        return $this->check($partRequest)[0];
    }

    /**
     * @return array{0: PaymentReconciliation, 1: string|null, 2: CarbonImmutable|null}
     *                                                                                  the outcome, the PaymentIntent id, and when its charge was made
     */
    private function check(PartRequest $partRequest): array
    {
        if ($partRequest->status !== RequestStatus::AwaitingPayment) {
            return [PaymentReconciliation::NothingToReconcile, null, null];
        }

        // The request's current Stripe payment: the latest one still open
        // on our side. (After a resumed-checkout replacement or a buyer's
        // cancel-and-start-over, older rows are failed + their intents
        // canceled at Stripe, so they can never have succeeded.)
        $payment = Payment::query()
            ->where('part_request_id', $partRequest->id)
            ->where('gateway', 'stripe')
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
            ->whereNotNull('gateway_reference')
            ->latest('id')
            ->first();

        if ($payment === null) {
            return [PaymentReconciliation::NothingToReconcile, null, null];
        }

        $context = ['part_request_id' => $partRequest->id, 'payment_id' => $payment->id];

        try {
            // latest_charge expanded for its timestamp: the moment the
            // money was actually taken.
            $intent = $this->stripe->paymentIntents->retrieve($payment->gateway_reference, ['expand' => ['latest_charge']]);
        } catch (ApiErrorException $e) {
            Log::channel('payments')->warning('Reconcile: could not retrieve PaymentIntent from Stripe', $context + [
                'stripe_code' => $e->getStripeCode(),
            ]);

            return [PaymentReconciliation::StripeUnavailable, null, null];
        }

        if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
            if ($intent->amount !== $payment->amount || strtolower($intent->currency) !== strtolower($payment->currency)) {
                Log::channel('payments')->critical('Reconcile: PaymentIntent succeeded for a different amount/currency than our record -- NOT confirmed, needs a human', $context);

                return [PaymentReconciliation::AmountMismatch, null, null];
            }

            return [PaymentReconciliation::Succeeded, $intent->id, $this->chargedAt($intent)];
        }

        if (in_array($intent->status, self::IN_FLIGHT, true)) {
            return [PaymentReconciliation::Processing, null, null];
        }

        return [PaymentReconciliation::NotPaid, null, null];
    }

    /**
     * The succeeded charge's own creation time, in the app timezone; null
     * (-> ConfirmStripePaymentAction falls back to now) if Stripe didn't
     * return the charge object.
     */
    private function chargedAt(PaymentIntent $intent): ?CarbonImmutable
    {
        $charge = $intent->latest_charge;

        return $charge instanceof Charge
            ? CarbonImmutable::createFromTimestamp($charge->created, config('app.timezone'))
            : null;
    }
}
