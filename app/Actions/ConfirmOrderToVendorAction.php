<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Models\PartRequest;

/**
 * The admin purchases from the chosen vendor, once the buyer's payment is
 * confirmed (CLAUDE.md §6.3's hard payment gate -- money-critical). The
 * vendor is derived from the request's own selectedResponse, not passed in
 * separately: CheckoutAction already requires selected_response_id to be
 * set before a request can reach `paid`, so which vendor to confirm to is
 * never a separate decision at this point.
 *
 * Deliberately minimal -- full slice 5 scope (CLAUDE.md §14 Phase 4:
 * delivery method + company address for the actual vendor purchase) isn't
 * built yet. This exists now specifically to make the payment gate real
 * and testable, per CLAUDE.md §6.3's explicit requirement, ahead of that
 * slice.
 */
class ConfirmOrderToVendorAction
{
    public function execute(PartRequest $partRequest): PartRequest
    {
        $paymentConfirmed = $partRequest->status === RequestStatus::Paid
            && $partRequest->payments()->where('status', PaymentStatus::Confirmed)->exists();

        if (! $paymentConfirmed) {
            throw PaymentNotConfirmedException::forRequest($partRequest);
        }

        $partRequest->update([
            'status' => RequestStatus::OrderedToVendor,
            'confirmed_vendor_id' => $partRequest->selectedResponse?->vendor_id,
        ]);

        return $partRequest->fresh();
    }
}
