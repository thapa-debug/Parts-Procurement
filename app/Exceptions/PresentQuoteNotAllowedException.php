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
            'responses (vendor_inquiry) can be quoted.'
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
}
