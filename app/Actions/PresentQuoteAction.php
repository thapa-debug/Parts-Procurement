<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\VendorResponse;
use App\Notifications\QuotePresentedNotification;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 見積もり提示: the admin presents a vendor response to the buyer as a priced
 * quote -- one of possibly several presented at once (client revision:
 * Phase 2 was one-shot, one-quote-per-request; this is incremental and
 * repeatable). Each call snapshots cost_price/applied_rate/applied_min_fee/
 * buyer_price onto its own new PresentedQuote row (CLAUDE.md §6.2 applied
 * per-quote) -- never recomputed afterward, so a later margin-rate change
 * can't drift what the buyer was already shown for an already-presented
 * option, even while a different option gets presented under a new rate.
 *
 * Allowed repeatedly, for as long as the request is still open: status must
 * be vendor_inquiry or quoted, AND the request must not yet have been paid
 * for (PartRequest::hasBeenPaid()). Checked as two explicit conditions even
 * though they're equivalent today (a paid status is never vendor_inquiry or
 * quoted) -- Phase 4 payment doesn't exist yet, so this is written to keep
 * reading correctly once it does, rather than relying on the status
 * whitelist alone staying in sync with whatever "paid" comes to mean.
 *
 * Deliberately does not touch vendor identity anywhere: only the response's
 * cost_price (via PricingService) and its own id are written onto the new
 * row. See PresentedQuote's own docblock and the buyer-facing view for the
 * isolation discipline this must never undermine.
 */
class PresentQuoteAction
{
    public function __construct(private PricingService $pricingService) {}

    public function execute(PartRequest $partRequest, VendorResponse $vendorResponse): PresentedQuote
    {
        $statusAllowsPresenting = in_array(
            $partRequest->status,
            [RequestStatus::VendorInquiry, RequestStatus::Quoted],
            true,
        );

        if (! $statusAllowsPresenting || $partRequest->hasBeenPaid()) {
            throw PresentQuoteNotAllowedException::wrongStatus($partRequest);
        }

        if ($vendorResponse->part_request_id !== $partRequest->id) {
            throw PresentQuoteNotAllowedException::responseMismatch();
        }

        if ($vendorResponse->is_no_stock || $vendorResponse->cost_price === null) {
            throw PresentQuoteNotAllowedException::noStockResponse();
        }

        $alreadyPresented = PresentedQuote::query()
            ->where('vendor_response_id', $vendorResponse->id)
            ->exists();

        if ($alreadyPresented) {
            throw PresentQuoteNotAllowedException::alreadyPresented();
        }

        $pricing = $this->pricingService->calculate($vendorResponse->cost_price);

        $presentedQuote = DB::transaction(function () use ($partRequest, $vendorResponse, $pricing) {
            $presentedQuote = PresentedQuote::create([
                'part_request_id' => $partRequest->id,
                'vendor_response_id' => $vendorResponse->id,
                'cost_price' => $pricing['cost_price'],
                'applied_rate' => $pricing['applied_rate'],
                'applied_min_fee' => $pricing['applied_min_fee'],
                'buyer_price' => $pricing['buyer_price'],
                'presented_at' => now(),
            ]);

            if ($partRequest->status === RequestStatus::VendorInquiry) {
                $partRequest->update(['status' => RequestStatus::Quoted]);
            }

            return $presentedQuote;
        });

        // Best-effort: a failed send must never undo a successful present
        // (CLAUDE.md §10) -- same try/catch(Throwable)+report() shape as
        // RegisterBuyerAction's verification email.
        try {
            $partRequest->buyer->user?->notify(new QuotePresentedNotification($presentedQuote));
        } catch (Throwable $e) {
            report($e);
        }

        return $presentedQuote;
    }
}
