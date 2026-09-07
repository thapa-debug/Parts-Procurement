<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Notifications\Notification;

/**
 * 打診: notifies one invited vendor that a request now needs their quote --
 * fired once per vendor from BroadcastRequestAction's loop. Database channel
 * only for this slice (Phase 3 Slice 1); mail is Slice 2.
 *
 * Isolation (CLAUDE.md §4): carries only what the vendor's own inbox
 * already shows them (request_code/part_name, see
 * resources/views/livewire/vendor/inbox.blade.php) -- never the buyer's
 * identity, and never anything about another vendor.
 */
class RequestBroadcastNotification extends Notification
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
            'type' => 'request_broadcast',
            'request_id' => $this->partRequest->id,
            'request_code' => $this->partRequest->request_code,
            'part_name' => $this->partRequest->part_name,
            'url' => route('vendor.inbox.show', $this->partRequest),
        ];
    }
}
