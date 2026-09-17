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

    /**
     * Defense-in-depth: shouldn't happen, since SelectQuoteAction always
     * sets shipping_fee alongside selected_response_id -- but never let
     * checkout silently charge for shipping it doesn't have a figure for.
     */
    public static function shippingFeeMissing(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} has no shipping fee set -- select a presented quote again before checking out."
        );
    }
}
