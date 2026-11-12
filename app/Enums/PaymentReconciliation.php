<?php

namespace App\Enums;

/**
 * What ReconcileStripePaymentAction found when it asked Stripe about an
 * awaiting_payment request's PaymentIntent (and what, if anything, it did).
 */
enum PaymentReconciliation: string
{
    /** Stripe says succeeded and this call confirmed the payment. */
    case Confirmed = 'confirmed';

    /** Stripe says succeeded, but it was already confirmed (e.g. the webhook won the race). */
    case AlreadyConfirmed = 'already_confirmed';

    /** inspect() only: Stripe says succeeded; execute() would confirm it. */
    case Succeeded = 'succeeded';

    /** Payment genuinely in flight at Stripe (processing / requires_capture). */
    case Processing = 'processing';

    /** Stripe has not taken the money (abandoned, declined, canceled...). */
    case NotPaid = 'not_paid';

    /** Stripe says succeeded but for a different amount/currency than our record -- never auto-confirmed. */
    case AmountMismatch = 'amount_mismatch';

    /** Not awaiting_payment, or no Stripe PaymentIntent on record -- Stripe was not asked. */
    case NothingToReconcile = 'nothing_to_reconcile';

    /** Stripe could not be reached; nothing changed. */
    case StripeUnavailable = 'stripe_unavailable';
}
