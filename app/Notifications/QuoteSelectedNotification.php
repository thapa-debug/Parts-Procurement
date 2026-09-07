<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a buyer picked (or re-picked) one of their
 * presented quotes -- fired from SelectQuoteAction, sent to all admins at
 * once via Notification::send(). Database channel only for this slice
 * (Phase 3 Slice 1); mail is Slice 2.
 *
 * No isolation concern: admin-facing, so the buyer's identity and the
 * snapshotted buyer_price are both fine to include (CLAUDE.md §4).
 * Deliberately does not resolve which vendor was picked -- the admin can
 * see that from the request detail page the link points to.
 */
class QuoteSelectedNotification extends Notification
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
            'type' => 'quote_selected',
            'request_id' => $this->partRequest->id,
            'request_code' => $this->partRequest->request_code,
            'buyer_company_name' => $this->partRequest->buyer->company_name,
            'buyer_price' => $this->partRequest->buyer_price,
            'url' => route('admin.requests.show', $this->partRequest->id),
        ];
    }
}
