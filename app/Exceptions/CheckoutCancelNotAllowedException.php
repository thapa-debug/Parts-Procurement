<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards CancelCheckoutAction (the buyer's "cancel this payment and start
 * over"). $alreadyPaid means Stripe already took the money -- the caller
 * must reconcile it, never cancel; $inProgress means it is in flight.
 */
class CheckoutCancelNotAllowedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $alreadyPaid = false,
        public readonly bool $inProgress = false,
    ) {
        parent::__construct($message);
    }

    public static function notAwaitingPayment(PartRequest $partRequest): self
    {
        return new self("Request {$partRequest->request_code} is not awaiting payment -- there is no checkout to cancel.");
    }

    public static function noPaymentToCancel(PartRequest $partRequest): self
    {
        return new self("Request {$partRequest->request_code} has no Stripe payment on record to cancel.");
    }

    public static function alreadyPaid(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code}'s PaymentIntent has succeeded -- the buyer has paid; reconcile, never cancel.",
            alreadyPaid: true,
        );
    }

    public static function paymentInProgress(PartRequest $partRequest, string $intentStatus): self
    {
        return new self(
            "Request {$partRequest->request_code}'s PaymentIntent is '{$intentStatus}' -- payment in flight, it can't be cancelled.",
            inProgress: true,
        );
    }

    public static function cancelFailed(PartRequest $partRequest): self
    {
        return new self("Request {$partRequest->request_code}'s PaymentIntent could not be canceled at Stripe -- nothing changed.");
    }
}
