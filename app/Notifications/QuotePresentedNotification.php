<?php

namespace App\Notifications;

use App\Models\PresentedQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the buyer that a new quote is available -- fired once per
 * presented quote from PresentQuoteAction (a batch present of several
 * quotes at once fires one of these per quote). Phase 3 Slice 2: mail
 * alongside the existing database (in-app) channel.
 *
 * Isolation (CLAUDE.md §4): carries only the request and the snapshotted,
 * already-marked-up buyer_price (CLAUDE.md §6.2) -- never the vendor's
 * identity, cost_price, or the underlying vendor_response/vendor_id, the
 * same allowlist discipline as Buyer\RequestDetail::presentedQuoteOptions().
 * Applies to both channels equally -- toMail() below carries exactly the
 * same allowlist as toArray().
 *
 * Queuing: implements ShouldQueue so the (slow, external) mail send never
 * blocks the request, but viaConnections() forces the database channel
 * through the 'sync' connection regardless of the app's configured queue
 * connection -- CLAUDE.md's "in-app notifications must stay synchronous"
 * requirement -- while mail (connection left null, i.e. "use the app's
 * default queue connection") gets deferred to a real worker. This is the
 * one Laravel mechanism that lets a single notification queue some
 * channels and not others; see NotificationSender::queueNotification(),
 * which dispatches one SendQueuedNotifications job per channel, each free
 * to pick its own connection.
 */
class QuotePresentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PresentedQuote $presentedQuote) {}

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
        return [
            'type' => 'quote_presented',
            'request_id' => $this->presentedQuote->part_request_id,
            'request_code' => $this->presentedQuote->partRequest->request_code,
            'buyer_price' => $this->presentedQuote->buyer_price,
            'url' => route('buyer.requests.show', $this->presentedQuote->part_request_id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requestCode = $this->presentedQuote->partRequest->request_code;
        $buyerPrice = number_format($this->presentedQuote->buyer_price);

        return (new MailMessage)
            ->subject(__('notifications.mail.quote_presented.subject', ['request_code' => $requestCode]))
            ->line(__('notifications.mail.quote_presented.line', ['request_code' => $requestCode, 'buyer_price' => $buyerPrice]))
            ->action(__('notifications.mail.quote_presented.action'), route('buyer.requests.show', $this->presentedQuote->part_request_id))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
