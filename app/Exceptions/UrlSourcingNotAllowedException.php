<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards the reference-URL (admin-sourced) flow -- SetUrlPriceAction and
 * MarkPurchasedFromUrlAction -- and keeps it and the vendor flow from ever
 * being mixed on one request (CLAUDE.md §5: transitions are guarded).
 */
class UrlSourcingNotAllowedException extends RuntimeException
{
    public static function wrongStatus(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} cannot be sourced from its reference URL from its ".
            "current status ({$partRequest->status->value}) -- only a new request, not yet sent to vendors, can."
        );
    }

    public static function noReferenceUrl(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} has no reference URL to source from."
        );
    }

    public static function invalidCost(): self
    {
        return new self('The URL cost must be a positive whole yen amount.');
    }

    public static function invalidWeight(): self
    {
        return new self('The weight must be greater than zero -- shipping is calculated from it.');
    }

    public static function invalidShippingFeeOverride(): self
    {
        return new self('An overridden shipping fee must be zero or more yen.');
    }

    /**
     * Same rule as PresentQuoteAction: an override is admin discretion and
     * must say why.
     */
    public static function overrideReasonRequired(): self
    {
        return new self('A reason is required when overriding the calculated shipping fee.');
    }

    /** MarkPurchasedFromUrlAction on a vendor-flow request. */
    public static function notUrlSourced(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} was not sourced from its reference URL -- ".
            'confirm the order to its vendor instead.'
        );
    }

    /**
     * A vendor-flow step (presenting a vendor quote, confirming to a
     * vendor) on a URL-sourced request -- there is no vendor on it.
     */
    public static function isUrlSourced(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} is sourced from its reference URL -- it has no vendor; ".
            'mark it purchased from the URL instead.'
        );
    }
}
