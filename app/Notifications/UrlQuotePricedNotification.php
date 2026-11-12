<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the buyer that their URL-sourced request has a price
 * (SetUrlPriceAction) -- the reference-URL flow's counterpart of
 * QuotePresentedNotification. To the buyer it is the same news ("a quote
 * is ready"), so it reuses that notification's type and wording: the
 * buyer is never told how the part was sourced.
 *
 * Isolation (CLAUDE.md §4): carries only the request and the marked-up
 * buyer_price -- never cost_price, the rate/margin, or the weight/shipping
 * override, on either channel. Same queue split as
 * QuotePresentedNotification: database synchronous, mail queued.
 */
class UrlQuotePricedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PartRequest $partRequest) {}

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
            'request_id' => $this->partRequest->id,
            'request_code' => $this->partRequest->request_code,
            'buyer_price' => $this->partRequest->buyer_price,
            'url' => route('buyer.requests.show', $this->partRequest->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requestCode = $this->partRequest->request_code;
        $buyerPrice = number_format((int) $this->partRequest->buyer_price);

        return (new MailMessage)
            ->subject(__('notifications.mail.quote_presented.subject', ['request_code' => $requestCode]))
            ->line(__('notifications.mail.quote_presented.line', ['request_code' => $requestCode, 'buyer_price' => $buyerPrice]))
            ->action(__('notifications.mail.quote_presented.action'), route('buyer.requests.show', $this->partRequest->id))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
