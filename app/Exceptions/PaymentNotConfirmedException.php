<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * CLAUDE.md §6.3's hard payment gate: guards ConfirmOrderToVendorAction.
 * The admin cannot purchase from a vendor until the buyer's payment is
 * confirmed -- money-critical, and gets its own test asserting a vendor
 * purchase can never be confirmed on an unpaid request.
 */
class PaymentNotConfirmedException extends RuntimeException
{
    public static function forRequest(PartRequest $partRequest): self
    {
        return new self(
            "Cannot confirm the vendor purchase for request {$partRequest->request_code} -- ".
            'payment has not been confirmed yet.'
        );
    }
}
