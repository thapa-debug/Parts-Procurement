<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\VendorResponse;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;

/**
 * 見積もり提示: the admin picks one vendor's response and presents it to the
 * buyer as a priced quote. Snapshots cost_price/applied_rate/applied_min_fee/
 * buyer_price onto the part_request (CLAUDE.md §6.2) -- these never get
 * recomputed from live settings afterward, so a later change to the margin
 * rate can't drift what a buyer was already shown. Records which response
 * was chosen (selected_response_id) and transitions vendor_inquiry -> quoted.
 *
 * Deliberately does not touch vendor identity anywhere: only the response's
 * cost_price (via PricingService) and its own id are written onto the
 * part_request. The buyer-facing view reads the snapshotted columns and
 * $partRequest->selectedResponse's photos/quality_rank/comment -- never
 * ->vendor -- see CLAUDE.md §4 isolation.
 */
class PresentQuoteAction
{
    public function __construct(private PricingService $pricingService) {}

    public function execute(PartRequest $partRequest, VendorResponse $vendorResponse): PartRequest
    {
        if ($partRequest->status !== RequestStatus::VendorInquiry) {
            throw PresentQuoteNotAllowedException::wrongStatus($partRequest);
        }

        if ($vendorResponse->part_request_id !== $partRequest->id) {
            throw PresentQuoteNotAllowedException::responseMismatch();
        }

        if ($vendorResponse->is_no_stock || $vendorResponse->cost_price === null) {
            throw PresentQuoteNotAllowedException::noStockResponse();
        }

        $pricing = $this->pricingService->calculate($vendorResponse->cost_price);

        return DB::transaction(function () use ($partRequest, $vendorResponse, $pricing) {
            $partRequest->update([
                'cost_price' => $pricing['cost_price'],
                'applied_rate' => $pricing['applied_rate'],
                'applied_min_fee' => $pricing['applied_min_fee'],
                'buyer_price' => $pricing['buyer_price'],
                'selected_response_id' => $vendorResponse->id,
                'status' => RequestStatus::Quoted,
            ]);

            return $partRequest->fresh();
        });
    }
}
