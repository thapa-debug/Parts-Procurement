<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Guards ShippingCalculator -- money-critical: never silently charge ¥0
 * shipping just because shipping_weight_brackets is misconfigured or empty.
 */
class ShippingBracketNotConfiguredException extends RuntimeException
{
    public static function forWeight(float $weightKg): self
    {
        return new self("No shipping weight bracket covers {$weightKg}kg -- check shipping_weight_brackets configuration.");
    }
}
