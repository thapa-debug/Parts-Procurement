<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells every admin that a payment is genuinely confirmed and CLAUDE.md
 * §6.3's payment gate is now open -- ConfirmOrderToVendorAction can be
 * called for this request. Actionable, not just informational: the whole
 * point is to prompt the admin to go confirm the order to the vendor now
 * that it's safe to. See PaymentConfirmedNotification's own docblock for
 * the shared firing point (SendPaymentConfirmedNotificationsAction) and
 * the three confirmation paths both notifications fire from.
 *
 * No isolation concern: admin-facing, so the buyer's identity and amount
 * paid are both fine to include (CLAUDE.md §4) -- same as
 * QuoteSelectedNotification. Still never includes which vendor will
 * fulfil it -- that's chosen at ConfirmOrderToVendorAction time, not here.
 *
 * `type` (and mail copy) branches on $partRequest->is_free the same way
 * PaymentConfirmedNotification's does -- "paid" would simply be
 * inaccurate wording for a 無償 request nothing was actually charged for.
 * A URL-sourced request (SetUrlPriceAction) gets its own copy as well:
 * there is no vendor to confirm to -- the admin buys from the reference
 * URL instead (MarkPurchasedFromUrlAction). See type().
 */
class PaymentConfirmedAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

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
        $partRequest = $this->payment->partRequest;

        return [
            'type' => $this->type(),
            'request_id' => $partRequest->id,
            'request_code' => $partRequest->request_code,
            'buyer_company_name' => $partRequest->buyer->company_name,
            'amount' => $this->payment->amount,
            'url' => route('admin.requests.show', $partRequest->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partRequest = $this->payment->partRequest;
        $buyerCompanyName = $partRequest->buyer->company_name;
        $requestCode = $partRequest->request_code;
        $url = route('admin.requests.show', $partRequest->id);

        if ($partRequest->is_url_sourced) {
            return (new MailMessage)
                ->subject(__('notifications.mail.payment_confirmed_admin_url.subject', [
                    'buyer_company_name' => $buyerCompanyName,
                    'request_code' => $requestCode,
                ]))
                ->line(__('notifications.mail.payment_confirmed_admin_url.line', [
                    'buyer_company_name' => $buyerCompanyName,
                    'request_code' => $requestCode,
                    'amount' => number_format($this->payment->amount),
                ]))
                ->action(__('notifications.mail.payment_confirmed_admin_url.action'), $url)
                ->line(__('notifications.mail.no_reply_notice'));
        }

        if ($partRequest->is_free) {
            return (new MailMessage)
                ->subject(__('notifications.mail.payment_confirmed_admin_free.subject', ['buyer_company_name' => $buyerCompanyName]))
                ->line(__('notifications.mail.payment_confirmed_admin_free.line', [
                    'buyer_company_name' => $buyerCompanyName,
                    'request_code' => $requestCode,
                ]))
                ->action(__('notifications.mail.payment_confirmed_admin_free.action'), $url)
                ->line(__('notifications.mail.no_reply_notice'));
        }

        return (new MailMessage)
            ->subject(__('notifications.mail.payment_confirmed_admin.subject', [
                'buyer_company_name' => $buyerCompanyName,
                'request_code' => $requestCode,
            ]))
            ->line(__('notifications.mail.payment_confirmed_admin.line', [
                'buyer_company_name' => $buyerCompanyName,
                'request_code' => $requestCode,
                'amount' => number_format($this->payment->amount),
            ]))
            ->action(__('notifications.mail.payment_confirmed_admin.action'), $url)
            ->line(__('notifications.mail.no_reply_notice'));
    }

    /**
     * Which copy this notification uses. A URL-sourced order has no vendor
     * (the admin buys from the reference URL), so it gets its own wording
     * rather than "confirm the order to the vendor". The URL flow is never
     * free, but URL is checked first so that wording can't regress if it
     * ever is.
     */
    private function type(): string
    {
        $partRequest = $this->payment->partRequest;

        return match (true) {
            (bool) $partRequest->is_url_sourced => 'payment_confirmed_admin_url',
            (bool) $partRequest->is_free => 'payment_confirmed_admin_free',
            default => 'payment_confirmed_admin',
        };
    }
}
