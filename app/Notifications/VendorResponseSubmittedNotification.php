<?php

namespace App\Notifications;

use App\Models\VendorResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies every admin user that a vendor has replied to a 打診 broadcast --
 * fired from SubmitVendorResponseAction, sent to all admins at once via
 * Notification::send(). Phase 3 Slice 2: mail alongside the existing
 * database (in-app) channel.
 *
 * No isolation concern: the admin is the only party that legitimately sees
 * both the buyer and the vendor (CLAUDE.md §4), so this is one of the
 * notifications in the set allowed to carry the vendor's identity. Applies
 * to both channels equally -- toMail() below carries the same content as
 * toArray().
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * forces the database channel through the 'sync' connection regardless of
 * the app's configured queue connection, while mail defers to a real
 * worker.
 */
class VendorResponseSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly VendorResponse $vendorResponse) {}

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
            'type' => 'vendor_response_submitted',
            'request_id' => $this->vendorResponse->part_request_id,
            'request_code' => $this->vendorResponse->partRequest->request_code,
            'vendor_company_name' => $this->vendorResponse->vendor->company_name,
            'is_no_stock' => $this->vendorResponse->is_no_stock,
            'url' => route('admin.requests.show', $this->vendorResponse->part_request_id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requestCode = $this->vendorResponse->partRequest->request_code;
        $vendorCompanyName = $this->vendorResponse->vendor->company_name;

        return (new MailMessage)
            ->subject(__('notifications.mail.vendor_response_submitted.subject', [
                'vendor_company_name' => $vendorCompanyName,
                'request_code' => $requestCode,
            ]))
            ->line(__('notifications.mail.vendor_response_submitted.line', [
                'vendor_company_name' => $vendorCompanyName,
                'request_code' => $requestCode,
            ]))
            ->action(__('notifications.mail.vendor_response_submitted.action'), route('admin.requests.show', $this->vendorResponse->part_request_id))
            ->line(__('notifications.mail.no_reply_notice'));
    }
}
