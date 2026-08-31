<?php

namespace App\Exceptions;

use RuntimeException;

class VendorResponseNotAllowedException extends RuntimeException
{
    public static function notInvited(): self
    {
        return new self('This vendor was not invited to quote on this request.');
    }

    public static function alreadyResponded(): self
    {
        return new self('This vendor has already responded to this request.');
    }
}
