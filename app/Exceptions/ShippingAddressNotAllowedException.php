<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Guards SnapshotShippingAddressAction -- the same defense-in-depth shape
 * as SelectQuoteNotAllowedException::responseMismatch().
 */
class ShippingAddressNotAllowedException extends RuntimeException
{
    public static function doesNotBelongToBuyer(): self
    {
        return new self("This shipping address does not belong to this request's buyer.");
    }
}
