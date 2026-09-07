<?php

namespace App\Actions;

use App\Exceptions\SelectQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use Illuminate\Support\Facades\DB;

/**
 * The buyer's pick among the currently presented quotes (client revision:
 * Phase 2 had no buyer-facing choice at all -- the admin's single presented
 * quote WAS the order). Copies the chosen PresentedQuote's own snapshot
 * (CLAUDE.md §6.2) onto part_requests' selected_response_id/cost_price/
 * applied_rate/applied_min_fee/buyer_price columns -- those columns now
 * mean "the buyer's current pick", not "what the admin presented", even
 * though nothing about their shape changed from Phase 2.
 *
 * Callable repeatedly: the buyer may change their mind and re-select a
 * different still-presented quote for as long as the request hasn't been
 * paid for yet (PartRequest::hasBeenPaid()). Each call simply overwrites
 * the previous selection -- it never throws for "already selected" -- and
 * it never touches any other PresentedQuote row; the ones not picked stay
 * presented, exactly as they were, as the admin's own record of what else
 * was on offer.
 */
class SelectQuoteAction
{
    public function execute(PartRequest $partRequest, PresentedQuote $presentedQuote): PartRequest
    {
        if ($partRequest->hasBeenPaid()) {
            throw SelectQuoteNotAllowedException::alreadyPaid($partRequest);
        }

        if ($presentedQuote->part_request_id !== $partRequest->id) {
            throw SelectQuoteNotAllowedException::responseMismatch();
        }

        return DB::transaction(function () use ($partRequest, $presentedQuote) {
            $partRequest->update([
                'selected_response_id' => $presentedQuote->vendor_response_id,
                'cost_price' => $presentedQuote->cost_price,
                'applied_rate' => $presentedQuote->applied_rate,
                'applied_min_fee' => $presentedQuote->applied_min_fee,
                'buyer_price' => $presentedQuote->buyer_price,
            ]);

            return $partRequest->fresh();
        });
    }
}
