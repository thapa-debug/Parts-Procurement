<?php

namespace App\Services;

use App\Exceptions\ShippingBracketNotConfiguredException;
use App\Models\ShippingWeightBracket;

/**
 * Rule-based shipping, v1 (CLAUDE.md §14 Phase 4): a deterministic weight
 * bracket lookup, admin-configurable via shipping_weight_brackets.
 * Weight-only -- size/dimensions and DHL are deferred to later slices.
 */
class ShippingCalculator
{
    /**
     * The smallest configured bracket whose upper_kg covers the given
     * weight -- brackets are inclusive at their own upper_kg (a weight
     * exactly on a bound uses that bound's bracket, not the next one up).
     * The null-upper_kg bracket, if configured, catches anything heavier
     * than every finite bracket.
     */
    public function calculate(float $weightKg): int
    {
        $bracket = ShippingWeightBracket::query()
            ->where(fn ($query) => $query->whereNull('upper_kg')->orWhere('upper_kg', '>=', $weightKg))
            ->orderBy('order')
            ->first();

        if ($bracket === null) {
            throw ShippingBracketNotConfiguredException::forWeight($weightKg);
        }

        return $bracket->fee;
    }
}
