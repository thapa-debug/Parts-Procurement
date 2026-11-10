<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards ResumeStripePaymentAction -- the abandoned-checkout resume path.
 * $inProgress separates "the payment is already going through, just wait
 * for the webhook" from "this payment session can't be used at all", so
 * the buyer gets the right message for each.
 */
class PaymentResumeNotAllowedException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $inProgress = false)
    {
        parent::__construct($message);
    }

    public static function notAwaitingPayment(PartRequest $partRequest): self
    {
        return new self("Request {$partRequest->request_code} is not awaiting payment -- there is no checkout to resume.");
    }

    public static function noResumablePayment(PartRequest $partRequest): self
    {
        return new self("Request {$partRequest->request_code} has no pending or failed Stripe payment to resume.");
    }

    public static function paymentInProgress(PartRequest $partRequest, string $intentStatus): self
    {
        return new self(
            "Request {$partRequest->request_code}'s PaymentIntent is '{$intentStatus}' -- already being paid; the webhook will confirm it.",
            inProgress: true,
        );
    }

    public static function intentNotUsable(PartRequest $partRequest, string $intentStatus): self
    {
        return new self("Request {$partRequest->request_code}'s PaymentIntent is '{$intentStatus}' and can no longer take a payment.");
    }

    public static function intentMismatch(PartRequest $partRequest): self
    {
        return new self("Request {$partRequest->request_code}'s PaymentIntent amount/currency does not match its payment record.");
    }
}
