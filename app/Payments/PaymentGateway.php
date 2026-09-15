<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * A clean, gateway-agnostic contract for taking a payment on a part_request
 * (CLAUDE.md §14 Phase 4 slice 1). Shaped loosely around Stripe
 * PaymentIntents -- the likely eventual gateway, still pending client
 * confirmation -- but generic enough to swap in another provider later.
 * Calling code must depend on this interface only; swapping the bound
 * implementation (config/payments.php) must require zero changes to any
 * caller.
 */
interface PaymentGateway
{
    /**
     * Charge the given (pending) payment through this gateway and report
     * the outcome. Implementations own translating their own provider's
     * response into a PaymentResult -- callers never inspect
     * gateway-specific data directly.
     */
    public function charge(Payment $payment): PaymentResult;

    /**
     * This gateway's own identifier (e.g. "stub", "stripe") -- what gets
     * written to payments.gateway. Lets a caller (CheckoutAction) populate
     * that NOT NULL column without knowing which concrete gateway is bound.
     */
    public function name(): string;
}
