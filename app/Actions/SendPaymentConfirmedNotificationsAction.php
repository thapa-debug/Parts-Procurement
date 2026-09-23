<?php

namespace App\Actions;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedAdminNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The single place that notifies both the buyer and every admin once a
 * payment is genuinely confirmed (CLAUDE.md §6.3: payments.status ===
 * Confirmed, not merely part_requests.status === paid -- those two can
 * disagree for a real Stripe charge, see StripePaymentGateway's own
 * docblock). Called from every path that can reach that state:
 *
 * - CheckoutAction, right after the stub gateway confirms synchronously
 *   inside charge() (dev/test only -- Stripe never does).
 * - ConfirmStripePaymentAction, the webhook-only confirmation point for a
 *   real Stripe charge.
 * - ConfirmFreeOrderAction, for a 無償 (free) request's ¥0 confirmed
 *   payment.
 *
 * A single-purpose Action wrapping two notify calls, rather than each of
 * the three callers duplicating this same pair -- CLAUDE.md §8's "one
 * class, one job" applied to composing two existing notifications, not a
 * generic NotificationService. Best-effort by design (CLAUDE.md §10): a
 * failed send here must never roll back, retry, or otherwise affect the
 * confirmation that already happened. Both notify calls are individually
 * caught -- the buyer notification failing must never suppress the admin
 * one, or vice versa, same as RegisterBuyerAction's verification-email/
 * admin-notification split -- so this action itself never throws, and
 * none of its three callers need their own try/catch around it.
 */
class SendPaymentConfirmedNotificationsAction
{
    public function execute(Payment $payment): void
    {
        $partRequest = $payment->partRequest;

        try {
            $partRequest->buyer->user?->notify(new PaymentConfirmedNotification($payment));
        } catch (Throwable $e) {
            report($e);
        }

        try {
            Notification::send(User::query()->admins()->get(), new PaymentConfirmedAdminNotification($payment));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
