<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * DEV/LOCAL ONLY. Assumes every charge succeeds and marks the payment
 * confirmed immediately -- there is no real gateway to call yet (CLAUDE.md
 * §14 Phase 4 slice 1: Stripe vs. another provider is still pending client
 * confirmation). PaymentServiceProvider refuses to bind this gateway in
 * production, whether PAYMENT_GATEWAY is left unset or explicitly set to
 * "stub" -- it can never become the production default.
 */
final class StubPaymentGateway implements PaymentGateway
{
    public function charge(Payment $payment): PaymentResult
    {
        $reference = 'stub_'.Str::uuid();
        $rawResponse = ['stub' => true];

        $payment->update([
            'status' => PaymentStatus::Confirmed,
            'gateway' => 'stub',
            'gateway_reference' => $reference,
            'raw_response' => $rawResponse,
            'paid_at' => now(),
        ]);

        return PaymentResult::success(gatewayReference: $reference, rawResponse: $rawResponse);
    }
}
