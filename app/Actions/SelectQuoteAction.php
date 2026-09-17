<?php

namespace App\Actions;

use App\Enums\ShippingMethod;
use App\Exceptions\SelectQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\User;
use App\Notifications\QuoteSelectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The buyer's pick among the currently presented quotes (client revision:
 * Phase 2 had no buyer-facing choice at all -- the admin's single presented
 * quote WAS the order). Copies the chosen PresentedQuote's own snapshot
 * (CLAUDE.md §6.2) onto part_requests' selected_response_id/cost_price/
 * applied_rate/applied_min_fee/buyer_price columns -- those columns now
 * mean "the buyer's current pick", not "what the admin presented", even
 * though nothing about their shape changed from Phase 2. Same for
 * shipping_fee (CLAUDE.md §14 Phase 4 rule-based shipping v1): copied here,
 * not computed at checkout, so it's already fixed by the time the buyer
 * reaches CheckoutAction -- shipping_method is set to Standard alongside
 * it (the only method a presented quote's fee can represent today; Dhl
 * isn't wired into this flow yet).
 *
 * Callable repeatedly: the buyer may change their mind and re-select a
 * different still-presented quote for as long as the request hasn't been
 * paid for yet (PartRequest::hasBeenPaid()). Each call simply overwrites
 * the previous selection -- it never throws for "already selected" -- and
 * it never touches any other PresentedQuote row; the ones not picked stay
 * presented, exactly as they were, as the admin's own record of what else
 * was on offer.
 *
 * is_free (CLAUDE.md §14 Phase 4 slice 5) is copied the same way as
 * buyer_price/shipping_fee -- PresentQuoteAction already guarantees every
 * presented quote on a given request agrees on is_free, so this is just
 * carrying that already-settled value onto the request, not deciding it.
 * CheckoutAction and ConfirmFreeOrderAction each guard against being called
 * on the wrong kind of request using this column.
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

        $partRequest = DB::transaction(function () use ($partRequest, $presentedQuote) {
            $partRequest->update([
                'selected_response_id' => $presentedQuote->vendor_response_id,
                'cost_price' => $presentedQuote->cost_price,
                'applied_rate' => $presentedQuote->applied_rate,
                'applied_min_fee' => $presentedQuote->applied_min_fee,
                'buyer_price' => $presentedQuote->buyer_price,
                'shipping_fee' => $presentedQuote->shipping_fee,
                'shipping_method' => ShippingMethod::Standard,
                'is_free' => $presentedQuote->is_free,
            ]);

            return $partRequest->fresh();
        });

        // Best-effort: a failed send must never undo a successful selection
        // (CLAUDE.md §10) -- same try/catch(Throwable)+report() shape as
        // RegisterBuyerAction's verification email.
        try {
            Notification::send(User::query()->admins()->get(), new QuoteSelectedNotification($partRequest));
        } catch (Throwable $e) {
            report($e);
        }

        return $partRequest;
    }
}
