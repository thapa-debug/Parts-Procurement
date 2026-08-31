<?php

namespace App\Exceptions;

use App\Models\PartRequest;
use RuntimeException;

/**
 * Guards BroadcastRequestAction's transition (CLAUDE.md §5: transitions are
 * guarded, never set status by hand).
 */
class RequestCannotBeBroadcastException extends RuntimeException
{
    public static function wrongStatus(PartRequest $partRequest): self
    {
        return new self(
            "Request {$partRequest->request_code} cannot be broadcast to vendors from its ".
            "current status ({$partRequest->status->value}) -- only a request still awaiting ".
            'its first inquiry (new) can be.'
        );
    }

    public static function noEligibleVendors(): self
    {
        return new self('At least one active (non-suspended) vendor must be selected to send an inquiry.');
    }
}
