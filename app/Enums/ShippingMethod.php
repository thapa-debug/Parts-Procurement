<?php

namespace App\Enums;

/**
 * CLAUDE.md §14 Phase 4: rule-based shipping v1 is a single calculated
 * method -- Standard -- so there's no longer a buyer-facing choice between
 * Vehicle/Container (removed). Dhl remains for the future second method,
 * not yet built.
 */
enum ShippingMethod: string
{
    case Standard = 'standard';
    case Dhl = 'dhl';
}
