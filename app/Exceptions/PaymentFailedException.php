<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Thrown by CheckoutAction when the gateway reports a failed charge --
 * rolls back the whole checkout transaction (CLAUDE.md §8/§10): no
 * snapshot, no shipping fee written, no status change, no stray payment
 * row left behind.
 */
class PaymentFailedException extends RuntimeException
{
    public static function chargeFailed(PartRequest $partRequest): self
    {
        return new self("Payment for request {$partRequest->request_code} was declined or failed. Nothing was charged.");
    }
}
