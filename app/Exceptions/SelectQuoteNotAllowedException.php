<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards SelectQuoteAction (CLAUDE.md §5: transitions/business-rule changes
 * are guarded, never done by hand).
 */
class SelectQuoteNotAllowedException extends RuntimeException
{
    public static function alreadyPaid(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} has already been paid for -- the selected ".
            'quote can no longer be changed.'
        );
    }

    public static function responseMismatch(): self
    {
        return new self('The selected quote does not belong to this request.');
    }
}
