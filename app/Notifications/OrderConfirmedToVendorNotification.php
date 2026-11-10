<?php

namespace App\Notifications;

use App\Models\PartRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 発注確定: tells the confirmed vendor their quote was selected and the
 * order is confirmed -- fired once from ConfirmOrderToVendorAction after
 * the confirmation commits. This is how the vendor learns they were
 * chosen, rather than having to open the request.
 *
 * Isolation (CLAUDE.md §4): carries only what the vendor's own confirmed-
 * order view already shows them (Vendor\RequestResponse) -- the request
 * code/part name, the company receiving address snapshot (receiving_*),
 * and their own delivery method. Never the buyer's identity or the
 * buyer's shipping_* address. The in-app data (toArray()) is narrower
 * still: just the receiving address's label, with the full address one
 * click away on the request page.
 *
 * Language: the vendor's preferredLocale() is 'ja', so the mail renders
 * from lang/ja/notifications.php (draft, pending client review).
 *
 * Queuing: see QuotePresentedNotification's docblock -- viaConnections()
 * keeps the database channel synchronous while mail defers to a worker.
 */
class OrderConfirmedToVendorNotification extends Notification implements ShouldQueue
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
            'type' => 'order_confirmed_to_vendor',
            'request_id' => $this->partRequest->id,
            'request_code' => $this->partRequest->request_code,
            'part_name' => $this->partRequest->part_name,
            'receiving_label' => $this->partRequest->receiving_label,
            'url' => route('vendor.inbox.show', $this->partRequest),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->partRequest;
        $deliveryMethod = $request->confirmedVendor?->delivery_method;

        $mail = (new MailMessage)
            ->subject(__('notifications.mail.order_confirmed_to_vendor.subject', [
                'part_name' => $request->part_name,
                'request_code' => $request->request_code,
            ]))
            ->line(__('notifications.mail.order_confirmed_to_vendor.line', [
                'part_name' => $request->part_name,
                'request_code' => $request->request_code,
            ]));

        if ($deliveryMethod !== null) {
            $mail->line(__('notifications.mail.order_confirmed_to_vendor.instruction.'.$deliveryMethod->value));
        }

        $mail->line(__('notifications.mail.order_confirmed_to_vendor.address_heading', ['label' => $request->receiving_label]))
            ->line(__('notifications.mail.order_confirmed_to_vendor.address', [
                'postal_code' => $request->receiving_postal_code,
                'prefecture' => $request->receiving_prefecture,
                'city' => $request->receiving_city,
                'address_line1' => $request->receiving_address_line1,
            ]));

        if ($request->receiving_address_line2) {
            $mail->line($request->receiving_address_line2);
        }

        $mail->line($request->receiving_recipient_name
            ? __('notifications.mail.order_confirmed_to_vendor.contact', ['recipient_name' => $request->receiving_recipient_name, 'phone' => $request->receiving_phone])
            : __('notifications.mail.order_confirmed_to_vendor.phone_only', ['phone' => $request->receiving_phone]));

        return $mail
            ->action(__('notifications.mail.order_confirmed_to_vendor.action'), route('vendor.inbox.show', $request))
            // Its own closing, not the shared no_reply_notice -- that one
            // asks the vendor to respond, and there's nothing to respond to.
            ->line(__('notifications.mail.order_confirmed_to_vendor.closing'));
    }
}
