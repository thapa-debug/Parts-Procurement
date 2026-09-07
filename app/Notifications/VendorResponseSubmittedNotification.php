<?php

namespace App\Notifications;

use App\Models\VendorResponse;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a vendor has replied to a 打診 broadcast --
 * fired from SubmitVendorResponseAction, sent to all admins at once via
 * Notification::send(). Database channel only for this slice (Phase 3
 * Slice 1); mail is Slice 2.
 *
 * No isolation concern: the admin is the only party that legitimately sees
 * both the buyer and the vendor (CLAUDE.md §4), so this is the one
 * notification in the set allowed to carry the vendor's identity.
 */
class VendorResponseSubmittedNotification extends Notification
{
    public function __construct(private readonly VendorResponse $vendorResponse) {}

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
            'type' => 'vendor_response_submitted',
            'request_id' => $this->vendorResponse->part_request_id,
            'request_code' => $this->vendorResponse->partRequest->request_code,
            'vendor_company_name' => $this->vendorResponse->vendor->company_name,
            'is_no_stock' => $this->vendorResponse->is_no_stock,
            'url' => route('admin.requests.show', $this->vendorResponse->part_request_id),
        ];
    }
}
