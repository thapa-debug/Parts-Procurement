<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\CheckoutCancelNotAllowedException;
use App\Models\PartRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * The buyer's "cancel this payment and start over" -- e.g. to change the
 * shipping address or method, which are locked once a PaymentIntent exists
 * for the total that includes them. Takes an awaiting_payment request back
 * to `quoted`, so the buyer can check out afresh (CheckoutAction then
 * creates one new intent for the newly computed total; the quote choice is
 * unlocked again too, and SelectQuoteAction re-copies the chosen quote's
 * snapshot as usual).
 *
 * Never cancels real money. Under a row lock on the request, it asks
 * Stripe for the intent's current state first:
 * - succeeded -> refused (alreadyPaid): the buyer HAS paid; the caller must
 *   reconcile it (ReconcileStripePaymentAction), never cancel it;
 * - processing / requires_capture -> refused (inProgress): money in flight;
 * - otherwise -> the intent is canceled at Stripe, and only once Stripe
 *   reports it `canceled` (so it can never be charged) is anything changed
 *   here: the payment row is kept as `failed` (never deleted, CLAUDE.md
 *   §6.4) and the request goes back to `quoted`.
 * If Stripe can't cancel it, nothing changes.
 */
class CancelCheckoutAction
{
    private const IN_FLIGHT = [PaymentIntent::STATUS_PROCESSING, PaymentIntent::STATUS_REQUIRES_CAPTURE];

    public function __construct(private readonly StripeClient $stripe) {}

    public function execute(PartRequest $partRequest): PartRequest
    {
        return DB::transaction(function () use ($partRequest) {
            $partRequest = PartRequest::query()->lockForUpdate()->findOrFail($partRequest->id);

            if ($partRequest->status !== RequestStatus::AwaitingPayment || $partRequest->is_free) {
                throw CheckoutCancelNotAllowedException::notAwaitingPayment($partRequest);
            }

            $payment = Payment::query()
                ->where('part_request_id', $partRequest->id)
                ->where('gateway', 'stripe')
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
                ->whereNotNull('gateway_reference')
                ->latest('id')
                ->first();

            if ($payment === null) {
                throw CheckoutCancelNotAllowedException::noPaymentToCancel($partRequest);
            }

            $context = ['part_request_id' => $partRequest->id, 'payment_id' => $payment->id];

            try {
                $intent = $this->stripe->paymentIntents->retrieve($payment->gateway_reference);

                if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
                    throw CheckoutCancelNotAllowedException::alreadyPaid($partRequest);
                }

                if (in_array($intent->status, self::IN_FLIGHT, true)) {
                    throw CheckoutCancelNotAllowedException::paymentInProgress($partRequest, $intent->status);
                }

                if ($intent->status !== PaymentIntent::STATUS_CANCELED) {
                    $intent = $this->stripe->paymentIntents->cancel($intent->id);
                }
            } catch (ApiErrorException $e) {
                Log::channel('payments')->warning('Checkout cancel refused: Stripe call failed', $context + ['stripe_code' => $e->getStripeCode()]);

                throw CheckoutCancelNotAllowedException::cancelFailed($partRequest);
            }

            if ($intent->status !== PaymentIntent::STATUS_CANCELED) {
                Log::channel('payments')->warning('Checkout cancel refused: PaymentIntent did not cancel', $context + ['intent_status' => $intent->status]);

                throw CheckoutCancelNotAllowedException::cancelFailed($partRequest);
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
                'raw_response' => array_merge($payment->raw_response ?? [], ['status' => PaymentIntent::STATUS_CANCELED, 'canceled_by' => 'buyer']),
            ]);

            $partRequest->update(['status' => RequestStatus::Quoted]);

            Log::channel('payments')->info('Checkout cancelled by the buyer; PaymentIntent canceled at Stripe, request back to quoted', $context);

            activity()
                ->performedOn($partRequest)
                ->withProperties(['payment_id' => $payment->id])
                ->log('Buyer cancelled checkout to start over');

            return $partRequest->fresh();
        });
    }
}
