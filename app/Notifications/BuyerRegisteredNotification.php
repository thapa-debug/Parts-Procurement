<?php

namespace App\Notifications;

use App\Models\BuyerProfile;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a buyer self-registered and is awaiting
 * approval (CLAUDE.md §14) -- fired from RegisterBuyerAction, sent to all
 * admins at once via Notification::send(). Database channel only for this
 * slice (Phase 3 Slice 1); mail is Slice 2.
 *
 * No isolation concern: admin-facing, so the buyer's own company name and
 * profile are fine to include (CLAUDE.md §4).
 */
class BuyerRegisteredNotification extends Notification
{
    public function __construct(private readonly BuyerProfile $buyerProfile) {}

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
            'type' => 'buyer_registered',
            'buyer_profile_id' => $this->buyerProfile->id,
            'buyer_company_name' => $this->buyerProfile->company_name,
            'url' => route('admin.buyers.show', $this->buyerProfile),
        ];
    }
}
