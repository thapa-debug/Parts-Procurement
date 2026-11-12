<?php

namespace App\Services;

use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\VendorResponse;

/**
 * The shipping fee for each method the buyer can pick at checkout -- the
 * one place both the checkout screen (to show each option's fee and the
 * live total) and CheckoutAction (to charge it) ask, so the two can never
 * disagree.
 *
 * Regular (ShippingMethod::Standard) is NOT recomputed: it's the fee
 * already snapshotted on the buyer's selected presented quote at
 * presentation time (ShippingCalculator's weight bracket, or the admin's
 * override), read back from that row -- the same never-recompute
 * discipline as pricing (CLAUDE.md §6.2). DHL comes from
 * DhlRateCalculator (a dummy fixed fee for now).
 *
 * A reference-URL (admin-sourced) request has no presented quote or
 * vendor response: its Regular fee is the one SetUrlPriceAction
 * snapshotted on url_shipping_fee, and DHL is rated from the weight the
 * admin entered (url_weight_kg). Same rules, different snapshot home.
 */
class ShippingFeeService
{
    public function __construct(private readonly DhlRateCalculator $dhl) {}

    public function feeFor(PartRequest $partRequest, ShippingMethod $method, BuyerAddress $destination): int
    {
        return match ($method) {
            ShippingMethod::Standard => $this->regularFee($partRequest),
            ShippingMethod::Dhl => $partRequest->is_url_sourced
                ? $this->dhl->calculateForWeight((float) $partRequest->url_weight_kg, $destination)
                : $this->dhl->calculate($this->selectedResponse($partRequest), $destination),
        };
    }

    /**
     * Every method's fee, keyed by its enum value -- what the checkout
     * screen switches between client-side for the live total.
     *
     * @return array<string, int>
     */
    public function feesFor(PartRequest $partRequest, BuyerAddress $destination): array
    {
        $fees = [];

        foreach (ShippingMethod::cases() as $method) {
            $fees[$method->value] = $this->feeFor($partRequest, $method, $destination);
        }

        return $fees;
    }

    /**
     * Regular needs no destination -- the checkout screen shows it even
     * before the buyer has picked an address.
     */
    public function regularFee(PartRequest $partRequest): int
    {
        $fee = $partRequest->is_url_sourced
            ? $partRequest->url_shipping_fee
            : PresentedQuote::query()
                ->where('part_request_id', $partRequest->id)
                ->where('vendor_response_id', $partRequest->selected_response_id)
                ->value('shipping_fee');

        if ($fee === null) {
            throw CheckoutNotAllowedException::shippingFeeMissing($partRequest);
        }

        return (int) $fee;
    }

    private function selectedResponse(PartRequest $partRequest): VendorResponse
    {
        return VendorResponse::query()
            ->where('part_request_id', $partRequest->id)
            ->findOrFail($partRequest->selected_response_id);
    }
}
