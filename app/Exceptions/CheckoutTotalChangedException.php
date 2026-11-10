<?php

namespace App\Exceptions;

use App\Models\PartRequest;

/**
 * The total the buyer was shown is not what CheckoutAction would charge
 * (e.g. they switched shipping method and clicked "Proceed to payment"
 * before the page caught up). Nothing is charged; the buyer is asked to
 * review the total and try again. A CheckoutNotAllowedException, so any
 * caller that already handles those still refuses safely.
 */
class CheckoutTotalChangedException extends CheckoutNotAllowedException
{
    public static function forTotals(PartRequest $partRequest, int $expected, int $actual): self
    {
        return new self(
            "Request {$partRequest->request_code}'s checkout total is ¥{$actual}, but the buyer was shown ¥{$expected} -- refusing to charge."
        );
    }
}
