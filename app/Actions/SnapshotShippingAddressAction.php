<?php

namespace App\Actions;

use App\Exceptions\ShippingAddressNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;

/**
 * Copies a buyer's chosen saved address onto the part_request (CLAUDE.md
 * §14 Phase 4 slice 2) -- the same snapshot-at-the-moment-it's-chosen
 * discipline as pricing (§6.2). Once written, later edits or deletes to
 * the source BuyerAddress must never change what this request shows it
 * shipped to; only the flattened shipping_* columns are the source of
 * truth afterward, not shipping_address_id (kept only for traceability
 * back to the live row while it still exists).
 *
 * Re-checks the address actually belongs to this request's own buyer
 * rather than trusting the caller -- the same defense-in-depth shape as
 * SelectQuoteAction's responseMismatch() check.
 *
 * Not called by anything yet -- Slice 3's checkout action is the intended
 * caller, once it exists.
 */
class SnapshotShippingAddressAction
{
    public function execute(PartRequest $partRequest, BuyerAddress $address): PartRequest
    {
        if ($address->buyer_id !== $partRequest->buyer_id) {
            throw ShippingAddressNotAllowedException::doesNotBelongToBuyer();
        }

        $partRequest->update([
            'shipping_address_id' => $address->id,
            'shipping_recipient_name' => $address->recipient_name,
            'shipping_phone' => $address->phone,
            'shipping_postal_code' => $address->postal_code,
            'shipping_country' => $address->country->name,
            'shipping_state' => $address->state,
            'shipping_city' => $address->city,
            'shipping_address_line1' => $address->address_line1,
            'shipping_address_line2' => $address->address_line2,
        ]);

        return $partRequest->fresh();
    }
}
