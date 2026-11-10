<?php

namespace App\Services;

use App\Models\BuyerAddress;
use App\Models\VendorResponse;

/**
 * DHL Express shipping fee (CLAUDE.md §14 Phase 4 slice 4).
 *
 * !! PLACEHOLDER -- NOT A REAL DHL RATE !!
 * Every shipment returns DUMMY_FEE, so the buyer's shipping-method choice
 * can be built and tested end to end before the client's DHL Express
 * account exists. The real calculation (MyDHL Rating API) replaces the
 * body of calculate() once it does. Its signature already carries what
 * that call needs -- the part's weight/dimensions (VendorResponse) and
 * where it's going (the buyer's address) -- so ShippingFeeService,
 * CheckoutAction and the checkout screen don't change when it lands.
 */
class DhlRateCalculator
{
    /** DUMMY placeholder fee in yen -- to be replaced by the DHL Rating API. */
    public const DUMMY_FEE = 15_000;

    public function calculate(VendorResponse $response, BuyerAddress $destination): int
    {
        return self::DUMMY_FEE;
    }
}
