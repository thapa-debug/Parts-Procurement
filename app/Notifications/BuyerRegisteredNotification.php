<?php

namespace App\Notifications;

use App\Models\BuyerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a buyer self-registered and is awaiting
 * approval (CLAUDE.md §14) -- fired from RegisterBuyerAction, sent to all
 * admins at once via Notification::send(). Phase 3 Slice 2: mail alongside
 * the existing database (in-app) channel.
 *
 * No isolation concern: admin-facing, so the buyer's own company name and
 * profile are fine to include (CLAUDE.md §4). Applies to both channels
 * equally -- toMail() below carries the same content as toArray().
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * forces the database channel through the 'sync' connection regardless of
 * the app's configured queue connection, while mail defers to a real
 * worker.
 */
class BuyerRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly BuyerProfile $buyerProfile) {}

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
            'type' => 'buyer_registered',
            'buyer_profile_id' => $this->buyerProfile->id,
            'buyer_company_name' => $this->buyerProfile->company_name,
            'url' => route('admin.buyers.show', $this->buyerProfile),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $buyerCompanyName = $this->buyerProfile->company_name;

        return (new MailMessage)
            ->subject(__('notifications.mail.buyer_registered.subject', ['buyer_company_name' => $buyerCompanyName]))
            ->line(__('notifications.mail.buyer_registered.line', ['buyer_company_name' => $buyerCompanyName]))
            ->action(__('notifications.mail.buyer_registered.action'), route('admin.buyers.show', $this->buyerProfile))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
