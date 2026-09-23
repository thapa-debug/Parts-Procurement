<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the buyer their payment is genuinely confirmed and the order is
 * moving forward -- fired only once a Payment row has actually reached
 * PaymentStatus::Confirmed (CLAUDE.md §6.3), never on the earlier
 * part_requests.status === paid alone (for a real Stripe charge those two
 * moments can be minutes apart -- see StripePaymentGateway's own
 * docblock). See SendPaymentConfirmedNotificationsAction, the single place
 * that constructs and sends this (and its admin counterpart,
 * PaymentConfirmedAdminNotification) for every confirmation path this app
 * has: the stub gateway (CheckoutAction, synchronous), Stripe
 * (ConfirmStripePaymentAction, webhook-only), and a 無償 free order
 * (ConfirmFreeOrderAction, ¥0).
 *
 * Isolation (CLAUDE.md §4): buyer-facing, so only the buyer's own amount
 * (what they paid, or nothing for a free order) and their own request code
 * are included -- no vendor identity, no cost_price, nothing about who
 * will fulfil the order.
 *
 * `type` (and which `notifications.mail.*` copy toMail() uses) branches on
 * $partRequest->is_free rather than one type string trying to read
 * correctly for both cases -- the same "two sibling paths, never one
 * shared path with a flag" discipline CheckoutAction/ConfirmFreeOrderAction
 * already follow (CLAUDE.md §8), applied to notification copy instead of
 * code.
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * forces the database channel through the 'sync' connection regardless of
 * the app's configured queue connection, while mail defers to a real
 * worker.
 */
class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $partRequest = $this->payment->partRequest;

        return [
            'type' => $partRequest->is_free ? 'payment_confirmed_free' : 'payment_confirmed',
            'request_id' => $partRequest->id,
            'request_code' => $partRequest->request_code,
            'amount' => $this->payment->amount,
            'url' => route('buyer.requests.show', $partRequest->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partRequest = $this->payment->partRequest;
        $requestCode = $partRequest->request_code;
        $url = route('buyer.requests.show', $partRequest->id);

        if ($partRequest->is_free) {
            return (new MailMessage)
                ->subject(__('notifications.mail.payment_confirmed_free.subject', ['request_code' => $requestCode]))
                ->line(__('notifications.mail.payment_confirmed_free.line', ['request_code' => $requestCode]))
                ->action(__('notifications.mail.payment_confirmed_free.action'), $url)
                ->line(__('notifications.mail.no_reply_notice'));
        }

        return (new MailMessage)
            ->subject(__('notifications.mail.payment_confirmed.subject', ['request_code' => $requestCode]))
            ->line(__('notifications.mail.payment_confirmed.line', [
                'request_code' => $requestCode,
                'amount' => number_format($this->payment->amount),
            ]))
            ->action(__('notifications.mail.payment_confirmed.action'), $url)
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
