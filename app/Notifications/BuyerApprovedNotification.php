<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the buyer that their account has been approved and they can now
 * pass the `act` gate (CLAUDE.md §14) -- fired from ApproveBuyerAction.
 * Phase 3 Slice 2: mail alongside the existing database (in-app) channel.
 *
 * No isolation concern and no per-recipient data at all: every buyer gets
 * the exact same message, so there is nothing here that could leak across
 * parties. Applies to both channels equally -- toMail() below carries the
 * same content as toArray().
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * forces the database channel through the 'sync' connection regardless of
 * the app's configured queue connection, while mail defers to a real
 * worker.
 */
class BuyerApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            'type' => 'buyer_approved',
            'url' => route('buyer.requests.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.mail.buyer_approved.subject'))
            ->line(__('notifications.mail.buyer_approved.line'))
            ->action(__('notifications.mail.buyer_approved.action'), route('buyer.requests.index'))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
