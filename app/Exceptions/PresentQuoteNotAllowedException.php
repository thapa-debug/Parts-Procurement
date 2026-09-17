<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards PresentQuoteAction's transition (CLAUDE.md §5: transitions are
 * guarded, never set status by hand).
 */
class PresentQuoteNotAllowedException extends RuntimeException
{
    public static function wrongStatus(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} cannot have a quote presented from its ".
            "current status ({$partRequest->status->value}) -- only a request awaiting vendor ".
            'responses (vendor_inquiry) or already showing presented quotes (quoted), and not yet '.
            'paid for, can have another quote presented.'
        );
    }

    public static function responseMismatch(): self
    {
        return new self('The selected vendor response does not belong to this request.');
    }

    public static function noStockResponse(): self
    {
        return new self('A "no stock" reply has no price and cannot be presented as a quote.');
    }

    public static function alreadyPresented(): self
    {
        return new self('This vendor response is already presented to the buyer.');
    }

    public static function missingWeight(): self
    {
        return new self('This vendor response has no weight recorded -- shipping cannot be calculated for it.');
    }

    public static function overrideReasonRequired(): self
    {
        return new self('A reason is required whenever the calculated shipping fee is overridden.');
    }

    public static function cannotOverrideFreeShipping(): self
    {
        return new self('A free (無償) quote has no shipping fee to override -- it is always ¥0.');
    }

    public static function mixedFreeAndPaidNotAllowed(): self
    {
        return new self(
            'This request already has a presented quote of the opposite free/paid kind -- '.
            'a request cannot mix free (無償) and paid quotes.'
        );
    }
}
