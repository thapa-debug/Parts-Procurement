<?php

namespace App\Payments;

/**
 * The gateway-agnostic outcome of PaymentGateway::charge() -- callers act
 * on this shape, never on a specific gateway's own response format.
 */
final readonly class PaymentResult
{
    private function __construct(
        public bool $successful,
        public ?string $gatewayReference,
        public array $rawResponse,
    ) {}

    public static function success(?string $gatewayReference = null, array $rawResponse = []): self
    {
        return new self(successful: true, gatewayReference: $gatewayReference, rawResponse: $rawResponse);
    }

    public static function failure(array $rawResponse = []): self
    {
        return new self(successful: false, gatewayReference: null, rawResponse: $rawResponse);
    }
}
