<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards CheckoutAction (CLAUDE.md §5: transitions/business-rule changes
 * are guarded, never done by hand).
 */
class CheckoutNotAllowedException extends RuntimeException
{
    public static function noQuoteSelected(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} cannot be checked out -- the buyer must have a ".
            'presented quote selected (status quoted, with a selected_response_id) before paying.'
        );
    }

    public static function dhlNotYetSupported(): self
    {
        return new self('DHL is not yet supported at checkout -- only vehicle and container shipping are available.');
    }
}
