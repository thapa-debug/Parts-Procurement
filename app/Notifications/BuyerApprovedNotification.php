<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifies the buyer that their account has been approved and they can now
 * pass the `act` gate (CLAUDE.md §14) -- fired from ApproveBuyerAction.
 * Database channel only for this slice (Phase 3 Slice 1); mail is Slice 2.
 *
 * No isolation concern and no per-recipient data at all: every buyer gets
 * the exact same message, so there is nothing here that could leak across
 * parties.
 */
class BuyerApprovedNotification extends Notification
{
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
            'type' => 'buyer_approved',
            'url' => route('buyer.requests.index'),
        ];
    }
}
