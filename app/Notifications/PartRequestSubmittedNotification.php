<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a buyer submitted a new part request --
 * fired from SubmitPartRequestAction, sent to all admins at once via
 * Notification::send(). Phase 3 Slice 2: mail alongside the existing
 * database (in-app) channel.
 *
 * No isolation concern: admin-facing, so the buyer's company name is fine
 * to include (CLAUDE.md §4). Applies to both channels equally -- toMail()
 * below carries the same content as toArray().
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * forces the database channel through the 'sync' connection regardless of
 * the app's configured queue connection, while mail defers to a real
 * worker.
 */
class PartRequestSubmittedNotification extends Notification implements ShouldQueue
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
            'type' => 'part_request_submitted',
            'request_id' => $this->partRequest->id,
            'request_code' => $this->partRequest->request_code,
            'buyer_company_name' => $this->partRequest->buyer->company_name,
            'part_name' => $this->partRequest->part_name,
            'url' => route('admin.requests.show', $this->partRequest->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $buyerCompanyName = $this->partRequest->buyer->company_name;
        $partName = $this->partRequest->part_name;

        return (new MailMessage)
            ->subject(__('notifications.mail.part_request_submitted.subject', ['part_name' => $partName]))
            ->line(__('notifications.mail.part_request_submitted.line', [
                'buyer_company_name' => $buyerCompanyName,
                'part_name' => $partName,
                'request_code' => $this->partRequest->request_code,
            ]))
            ->action(__('notifications.mail.part_request_submitted.action'), route('admin.requests.show', $this->partRequest->id))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
