<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards ConfirmFreeOrderAction (CLAUDE.md §5: transitions/business-rule
 * changes are guarded, never done by hand) -- the 無償 mirror of
 * CheckoutNotAllowedException.
 */
class FreeOrderNotAllowedException extends RuntimeException
{
    /**
     * Defense-in-depth: shouldn't happen, since a buyer only ever reaches
     * this action through the free-order confirmation screen, which itself
     * only renders for an is_free request -- but never let a paid request
     * skip the real payment gateway by mistake.
     */
    public static function notFree(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} is not a free (無償) request -- it must be paid ".
            'for through checkout instead of confirmed here.'
        );
    }

    public static function noQuoteSelected(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} cannot be confirmed -- the buyer must have a ".
            'presented quote selected (status quoted, with a selected_response_id) first.'
        );
    }
}
