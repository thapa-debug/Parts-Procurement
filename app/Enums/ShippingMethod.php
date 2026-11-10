<?php

namespace App\Enums;

/**
 * The buyer's shipping choice at checkout (CLAUDE.md §14 Phase 4).
 * Standard is shown to buyers as "Regular": the rule-based weight-bracket
 * fee fixed at quote presentation, and the default. Dhl's fee comes from
 * DhlRateCalculator (a dummy fixed fee until the real DHL API is wired).
 * Vehicle/Container were removed in rule-based shipping v1.
 */
enum ShippingMethod: string
{
    case Standard = 'standard';
    case Dhl = 'dhl';
}
