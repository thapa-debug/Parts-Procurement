<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 打診: notifies one invited vendor that a request now needs their quote --
 * fired once per vendor from BroadcastRequestAction's loop. Phase 3 Slice 2:
 * mail alongside the existing database (in-app) channel.
 *
 * Isolation (CLAUDE.md §4): carries only what the vendor's own inbox
 * already shows them (request_code/part_name, see
 * resources/views/livewire/vendor/inbox.blade.php) -- never the buyer's
 * identity, and never anything about another vendor. Applies to both
 * channels equally -- toMail() below carries exactly the same allowlist
 * as toArray().
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * forces the database channel through the 'sync' connection regardless of
 * the app's configured queue connection, while mail defers to a real
 * worker.
 */
class RequestBroadcastNotification extends Notification implements ShouldQueue
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
            'type' => 'request_broadcast',
            'request_id' => $this->partRequest->id,
            'request_code' => $this->partRequest->request_code,
            'part_name' => $this->partRequest->part_name,
            'url' => route('vendor.inbox.show', $this->partRequest),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.mail.request_broadcast.subject', ['part_name' => $this->partRequest->part_name]))
            ->line(__('notifications.mail.request_broadcast.line', [
                'part_name' => $this->partRequest->part_name,
                'request_code' => $this->partRequest->request_code,
            ]))
            ->action(__('notifications.mail.request_broadcast.action'), route('vendor.inbox.show', $this->partRequest))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
