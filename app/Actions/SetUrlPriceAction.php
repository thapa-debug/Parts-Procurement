<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\UrlSourcingNotAllowedException;
use App\Models\PartRequest;
use App\Notifications\UrlQuotePricedNotification;
use App\Services\PricingService;
use App\Services\ShippingCalculator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reference-URL (admin-sourced) flow, front half: instead of broadcasting
 * a request to vendors (打診), the admin sources it directly from the
 * buyer's own reference_url, enters what it costs there, and this action
 * prices it. It replaces the whole vendor broadcast → response → present
 * → select sequence for that request, and leaves it exactly where
 * SelectQuoteAction leaves a vendor request: `quoted`, priced, ready for
 * the existing checkout.
 *
 * Pricing (CLAUDE.md §6.1/§6.2): the URL cost goes through the same
 * PricingService as a vendor's cost (rate + min-fee floor), and
 * cost_price / applied_rate / applied_min_fee / buyer_price are
 * snapshotted onto the request -- never recomputed from live settings.
 *
 * Shipping: the same rule-based weight bracket as a vendor quote
 * (ShippingCalculator), from the weight the admin enters (there is no
 * vendor response to carry one), with the same optional admin override
 * that requires a reason. The Regular fee is snapshotted on
 * url_shipping_fee -- what ShippingFeeService reads at checkout -- and
 * also copied to shipping_fee/shipping_method (Regular, the default),
 * the same as SelectQuoteAction does.
 *
 * Only from `new` (not yet broadcast -- a request takes one front half or
 * the other, never both), only with a reference_url, and only once:
 * pricing is final, like presenting a vendor quote. The row is locked and
 * re-checked, so two admins pricing the same request at once can't both
 * succeed. Never free: 無償 stays a vendor-flow decision for now.
 */
class SetUrlPriceAction
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly ShippingCalculator $shippingCalculator,
    ) {}

    public function execute(
        PartRequest $partRequest,
        int $urlCost,
        float $weightKg,
        ?int $shippingFeeOverride = null,
        ?string $shippingFeeOverrideReason = null,
    ): PartRequest {
        if ($urlCost <= 0) {
            throw UrlSourcingNotAllowedException::invalidCost();
        }

        if ($weightKg <= 0) {
            throw UrlSourcingNotAllowedException::invalidWeight();
        }

        if ($shippingFeeOverride !== null && $shippingFeeOverride < 0) {
            throw UrlSourcingNotAllowedException::invalidShippingFeeOverride();
        }

        if ($shippingFeeOverride !== null && trim((string) $shippingFeeOverrideReason) === '') {
            throw UrlSourcingNotAllowedException::overrideReasonRequired();
        }

        $partRequest = DB::transaction(function () use ($partRequest, $urlCost, $weightKg, $shippingFeeOverride, $shippingFeeOverrideReason) {
            $partRequest = PartRequest::query()->lockForUpdate()->findOrFail($partRequest->id);

            if ($partRequest->status !== RequestStatus::New || $partRequest->is_url_sourced) {
                throw UrlSourcingNotAllowedException::wrongStatus($partRequest);
            }

            if (trim((string) $partRequest->reference_url) === '') {
                throw UrlSourcingNotAllowedException::noReferenceUrl($partRequest);
            }

            $pricing = $this->pricingService->calculate($urlCost);
            $isOverridden = $shippingFeeOverride !== null;
            $shippingFee = $shippingFeeOverride ?? $this->shippingCalculator->calculate($weightKg);

            $partRequest->update([
                'is_url_sourced' => true,
                'status' => RequestStatus::Quoted,
                'cost_price' => $pricing['cost_price'],
                'applied_rate' => $pricing['applied_rate'],
                'applied_min_fee' => $pricing['applied_min_fee'],
                'buyer_price' => $pricing['buyer_price'],
                'url_weight_kg' => $weightKg,
                'url_shipping_fee' => $shippingFee,
                'url_shipping_fee_overridden' => $isOverridden,
                'url_shipping_fee_override_reason' => $isOverridden ? $shippingFeeOverrideReason : null,
                'shipping_fee' => $shippingFee,
                'shipping_method' => ShippingMethod::Standard,
            ]);

            // Who priced it, at what cost and margin (CLAUDE.md §11).
            // Admin-only: the activity log is never shown to the buyer.
            activity()
                ->performedOn($partRequest)
                ->event('url_priced')
                ->withProperties([
                    'cost_price' => $pricing['cost_price'],
                    'applied_rate' => $pricing['applied_rate'],
                    'applied_min_fee' => $pricing['applied_min_fee'],
                    'buyer_price' => $pricing['buyer_price'],
                    'weight_kg' => $weightKg,
                    'shipping_fee' => $shippingFee,
                    'shipping_fee_overridden' => $isOverridden,
                    'shipping_fee_override_reason' => $isOverridden ? $shippingFeeOverrideReason : null,
                ])
                ->log('Priced from reference URL');

            return $partRequest->fresh();
        });

        // Best-effort: a failed send must never undo a successful pricing
        // (CLAUDE.md §10) -- same shape as PresentQuoteAction.
        try {
            $partRequest->buyer->user?->notify(new UrlQuotePricedNotification($partRequest));
        } catch (Throwable $e) {
            report($e);
        }

        return $partRequest;
    }
}
