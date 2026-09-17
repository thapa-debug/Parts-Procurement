<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\VendorResponse;
use App\Notifications\QuotePresentedNotification;
use App\Services\PricingService;
use App\Services\ShippingCalculator;
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
 *
 * Shipping (CLAUDE.md §14 Phase 4, rule-based v1): the fee is computed here
 * -- at presentation, not at checkout -- via ShippingCalculator, from the
 * response's own weight_kg. The admin may pass $shippingFeeOverride to use
 * a different figure instead, but only alongside a non-empty
 * $shippingFeeOverrideReason -- required, stored, and activity-logged
 * (PresentedQuote::getActivitylogOptions()), admin-internal and never
 * shown to the buyer.
 *
 * 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5): $isFree is a purely
 * admin-discretionary flag, never derived from anything else (not vehicle
 * ownership, not any external system). When true, buyer_price and
 * shipping_fee are forced to 0 on the new row regardless of the normal
 * calculation -- cost_price/applied_rate/applied_min_fee are still the
 * real PricingService figures, so the admin's own accounting keeps
 * showing what was actually owed to the vendor. $isFree and
 * $shippingFeeOverride are mutually exclusive (there is nothing to
 * override on a fee that's forced to zero). A request may not mix free
 * and paid presented quotes -- once one is presented, every later one for
 * the same request must match its is_free value, enforced here rather
 * than left to SelectQuoteAction/checkout to discover.
 */
class PresentQuoteAction
{
    public function __construct(
        private PricingService $pricingService,
        private ShippingCalculator $shippingCalculator,
    ) {}

    public function execute(
        PartRequest $partRequest,
        VendorResponse $vendorResponse,
        ?int $shippingFeeOverride = null,
        ?string $shippingFeeOverrideReason = null,
        bool $isFree = false,
    ): PresentedQuote {
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

        if ($vendorResponse->weight_kg === null) {
            throw PresentQuoteNotAllowedException::missingWeight();
        }

        if ($isFree && $shippingFeeOverride !== null) {
            throw PresentQuoteNotAllowedException::cannotOverrideFreeShipping();
        }

        if ($shippingFeeOverride !== null && trim((string) $shippingFeeOverrideReason) === '') {
            throw PresentQuoteNotAllowedException::overrideReasonRequired();
        }

        $alreadyPresented = PresentedQuote::query()
            ->where('vendor_response_id', $vendorResponse->id)
            ->exists();

        if ($alreadyPresented) {
            throw PresentQuoteNotAllowedException::alreadyPresented();
        }

        $mixesFreeAndPaid = PresentedQuote::query()
            ->where('part_request_id', $partRequest->id)
            ->where('is_free', ! $isFree)
            ->exists();

        if ($mixesFreeAndPaid) {
            throw PresentQuoteNotAllowedException::mixedFreeAndPaidNotAllowed();
        }

        $pricing = $this->pricingService->calculate($vendorResponse->cost_price);

        $isOverridden = $shippingFeeOverride !== null;
        $shippingFee = $isFree
            ? 0
            : ($shippingFeeOverride ?? $this->shippingCalculator->calculate((float) $vendorResponse->weight_kg));
        $buyerPrice = $isFree ? 0 : $pricing['buyer_price'];

        $presentedQuote = DB::transaction(function () use ($partRequest, $vendorResponse, $pricing, $buyerPrice, $shippingFee, $isOverridden, $shippingFeeOverrideReason, $isFree) {
            $presentedQuote = PresentedQuote::create([
                'part_request_id' => $partRequest->id,
                'vendor_response_id' => $vendorResponse->id,
                'cost_price' => $pricing['cost_price'],
                'applied_rate' => $pricing['applied_rate'],
                'applied_min_fee' => $pricing['applied_min_fee'],
                'buyer_price' => $buyerPrice,
                'presented_at' => now(),
                'shipping_fee' => $shippingFee,
                'shipping_fee_overridden' => $isOverridden,
                'shipping_fee_override_reason' => $isOverridden ? $shippingFeeOverrideReason : null,
                'is_free' => $isFree,
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
