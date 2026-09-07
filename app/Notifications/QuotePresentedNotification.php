<?php

namespace App\Notifications;

use App\Models\PresentedQuote;
use Illuminate\Notifications\Notification;

/**
 * Notifies the buyer that a new quote is available -- fired once per
 * presented quote from PresentQuoteAction (a batch present of several
 * quotes at once fires one of these per quote). Database channel only for
 * this slice (Phase 3 Slice 1); mail is Slice 2.
 *
 * Isolation (CLAUDE.md §4): carries only the request and the snapshotted,
 * already-marked-up buyer_price (CLAUDE.md §6.2) -- never the vendor's
 * identity, cost_price, or the underlying vendor_response/vendor_id, the
 * same allowlist discipline as Buyer\RequestDetail::presentedQuoteOptions().
 */
class QuotePresentedNotification extends Notification
{
    public function __construct(private readonly PresentedQuote $presentedQuote) {}

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
            'type' => 'quote_presented',
            'request_id' => $this->presentedQuote->part_request_id,
            'request_code' => $this->presentedQuote->partRequest->request_code,
            'buyer_price' => $this->presentedQuote->buyer_price,
            'url' => route('buyer.requests.show', $this->presentedQuote->part_request_id),
        ];
    }
}
