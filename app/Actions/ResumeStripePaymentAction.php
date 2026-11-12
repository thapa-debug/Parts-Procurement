<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\PaymentResumeNotAllowedException;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Payments\StripePaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Lets a buyer who abandoned the card form come back and finish paying --
 * by resuming the SAME PaymentIntent CheckoutAction created, never a new
 * one. One live PaymentIntent per request is what makes a double charge
 * impossible, so this RETRIEVES the intent by its stored id
 * (payments.gateway_reference) and hands its client secret back for the
 * card form to mount against. It changes nothing on our side: the request
 * stays `awaiting_payment` and the payment stays as it was until Stripe's
 * webhook confirms it (ConfirmStripePaymentAction, the only thing that
 * ever marks a payment confirmed).
 *
 * An intent that is mid-payment or already succeeded is left to the
 * webhook. One whose amount/currency no longer matches our record is
 * refused (a data anomaly for a human to look at, never auto-repaired).
 *
 * The one case that gets a fresh intent: Stripe will no longer take a
 * card on the stored one (e.g. it was canceled). Even then a replacement
 * is only ever created once the old intent is confirmed `canceled` at
 * Stripe -- cancelled by us first if it isn't already -- so there is never
 * more than one intent able to take money. The old payment row is kept
 * (marked failed, never deleted, CLAUDE.md §6.4) and a new pending row is
 * created for the replacement. The whole thing runs under a row lock on
 * the request, so two tabs resuming at once can't each create one.
 */
class ResumeStripePaymentAction
{
    /** Stripe PaymentIntent statuses that can still take the buyer's card. */
    private const RESUMABLE = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    /** Stripe statuses where the payment is genuinely in flight. */
    private const IN_PROGRESS = ['processing', 'requires_capture'];

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripePaymentGateway $gateway,
    ) {}

    /**
     * @return string the PaymentIntent's client secret -- never persisted
     *                or logged (Stripe: share only with the customer)
     */
    public function execute(PartRequest $partRequest): string
    {
        if ($partRequest->status !== RequestStatus::AwaitingPayment) {
            throw PaymentResumeNotAllowedException::notAwaitingPayment($partRequest);
        }

        return DB::transaction(function () use ($partRequest) {
            // Re-read under the lock: the webhook may have moved it to
            // `paid`, or another tab may have replaced the intent, since
            // the caller loaded it.
            $partRequest = PartRequest::query()->lockForUpdate()->findOrFail($partRequest->id);

            if ($partRequest->status !== RequestStatus::AwaitingPayment) {
                throw PaymentResumeNotAllowedException::notAwaitingPayment($partRequest);
            }

            $payment = Payment::query()
                ->where('part_request_id', $partRequest->id)
                ->where('gateway', 'stripe')
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
                ->whereNotNull('gateway_reference')
                ->latest('id')
                ->first();

            if ($payment === null) {
                throw PaymentResumeNotAllowedException::noResumablePayment($partRequest);
            }

            $intent = $this->stripe->paymentIntents->retrieve($payment->gateway_reference);

            // Already paid at Stripe (e.g. the webhook never arrived): never
            // resume or replace it -- the caller reconciles instead.
            if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
                throw PaymentResumeNotAllowedException::alreadyPaid($partRequest);
            }

            if (in_array($intent->status, self::IN_PROGRESS, true)) {
                throw PaymentResumeNotAllowedException::paymentInProgress($partRequest, $intent->status);
            }

            if (! in_array($intent->status, self::RESUMABLE, true)) {
                return $this->replaceUnusableIntent($partRequest, $payment, $intent);
            }

            // Defense in depth: the intent must still be for exactly what our
            // payment record says (the quote choice is locked, so it should be).
            if ($intent->amount !== $payment->amount || strtolower($intent->currency) !== strtolower($payment->currency)) {
                Log::channel('payments')->warning('Checkout resume refused: PaymentIntent does not match its payment record', [
                    'part_request_id' => $partRequest->id,
                    'payment_id' => $payment->id,
                ]);

                throw PaymentResumeNotAllowedException::intentMismatch($partRequest);
            }

            Log::channel('payments')->info('Checkout resumed on the existing PaymentIntent', [
                'part_request_id' => $partRequest->id,
                'payment_id' => $payment->id,
            ]);

            return $intent->client_secret;
        });
    }

    /**
     * The old intent can't take a card any more. Make sure it is canceled
     * at Stripe -- so it can never be charged alongside its replacement --
     * then open exactly one new pending payment + intent for the same
     * snapshotted total CheckoutAction charged (buyer_price + shipping_fee).
     */
    private function replaceUnusableIntent(PartRequest $partRequest, Payment $payment, PaymentIntent $intent): string
    {
        $context = [
            'part_request_id' => $partRequest->id,
            'payment_id' => $payment->id,
            'intent_status' => $intent->status,
        ];

        if ($intent->status !== PaymentIntent::STATUS_CANCELED) {
            try {
                $intent = $this->stripe->paymentIntents->cancel($intent->id);
            } catch (ApiErrorException $e) {
                Log::channel('payments')->warning('Checkout resume refused: unusable PaymentIntent could not be canceled', $context + [
                    'stripe_code' => $e->getStripeCode(),
                ]);

                throw PaymentResumeNotAllowedException::intentNotUsable($partRequest, $context['intent_status']);
            }

            if ($intent->status !== PaymentIntent::STATUS_CANCELED) {
                Log::channel('payments')->warning('Checkout resume refused: PaymentIntent did not cancel', $context);

                throw PaymentResumeNotAllowedException::intentNotUsable($partRequest, $intent->status);
            }
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'raw_response' => array_merge($payment->raw_response ?? [], ['status' => PaymentIntent::STATUS_CANCELED]),
        ]);

        $replacement = Payment::create([
            'part_request_id' => $partRequest->id,
            'amount' => $partRequest->buyer_price + $partRequest->shipping_fee,
            'currency' => 'JPY',
            'status' => PaymentStatus::Pending,
            'gateway' => $this->gateway->name(),
        ]);

        $result = $this->gateway->charge($replacement);

        if (! $result->successful) {
            Log::channel('payments')->warning('Checkout resume: replacement PaymentIntent could not be created', $context);

            throw PaymentFailedException::chargeFailed($partRequest);
        }

        Log::channel('payments')->info('Checkout resumed on a replacement PaymentIntent, the old one canceled', $context + [
            'replacement_payment_id' => $replacement->id,
        ]);

        return $result->rawResponse['client_secret'];
    }
}
