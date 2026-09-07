<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a buyer submitted a new part request --
 * fired from SubmitPartRequestAction, sent to all admins at once via
 * Notification::send(). Database channel only for this slice (Phase 3
 * Slice 1); mail is Slice 2.
 *
 * No isolation concern: admin-facing, so the buyer's company name is fine
 * to include (CLAUDE.md §4).
 */
class PartRequestSubmittedNotification extends Notification
{
    public function __construct(private readonly PartRequest $partRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
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
}
