<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\PaymentResult;
use App\Payments\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * Swaps Stripe's own HTTP transport (CLAUDE.md §9: tests must never hit
 * the real Stripe API) so the real stripe-php SDK code -- request
 * building, response parsing, PaymentIntent hydration -- all runs for
 * real; only the network call itself is faked. Far less brittle than
 * mocking the SDK's own lazily-constructed service objects.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, absUrl: string, headers: array<int, string>, params: array<string, mixed>}> */
    public array $requests = [];

    public function __construct(
        private readonly int $statusCode,
        private readonly array $responseBody,
    ) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'absUrl' => $absUrl, 'headers' => $headers, 'params' => $params];

        return [json_encode($this->responseBody), $this->statusCode, []];
    }
}

function stripeGatewayWith(FakeStripeHttpClient $fake): StripePaymentGateway
{
    ApiRequestor::setHttpClient($fake);

    return new StripePaymentGateway(new StripeClient('sk_test_fake'));
}

afterEach(function () {
    // Never let a faked transport leak into another test file.
    ApiRequestor::setHttpClient(null);
});

it('creates a PaymentIntent and returns success, without marking the payment confirmed', function () {
    $payment = Payment::factory()->create(['amount' => 54_000, 'currency' => 'JPY', 'gateway' => 'stripe']);

    $fake = new FakeStripeHttpClient(200, [
        'id' => 'pi_test_123',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_test_123_secret_abc',
        'amount' => 54_000,
        'currency' => 'jpy',
    ]);

    $result = stripeGatewayWith($fake)->charge($payment);

    expect($result)->toBeInstanceOf(PaymentResult::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->gatewayReference)->toBe('pi_test_123')
        ->and($result->rawResponse['client_secret'])->toBe('pi_test_123_secret_abc');

    $payment->refresh();

    // The whole point of this gateway (CLAUDE.md §6.3/§14 stripe
    // integration): creating the intent is NOT confirmation. Only the
    // webhook (ConfirmStripePaymentAction) is ever allowed to do that.
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->paid_at)->toBeNull()
        ->and($payment->gateway_reference)->toBe('pi_test_123');
});

it('never persists the client secret onto the payment row', function () {
    $payment = Payment::factory()->create(['amount' => 10_000, 'currency' => 'JPY', 'gateway' => 'stripe']);

    $fake = new FakeStripeHttpClient(200, [
        'id' => 'pi_test_456',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_test_456_secret_should_not_be_stored',
        'amount' => 10_000,
        'currency' => 'jpy',
    ]);

    stripeGatewayWith($fake)->charge($payment);

    $stored = $payment->refresh()->raw_response;

    expect($stored)->not->toBeNull()
        ->and($stored)->not->toHaveKey('client_secret')
        ->and(json_encode($stored))->not->toContain('secret_should_not_be_stored');
});

it('sends the amount as-is for JPY, a zero-decimal currency -- never multiplied by 100', function () {
    $payment = Payment::factory()->create(['amount' => 123_456, 'currency' => 'JPY', 'gateway' => 'stripe']);

    $fake = new FakeStripeHttpClient(200, [
        'id' => 'pi_test_amount',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'secret',
        'amount' => 123_456,
        'currency' => 'jpy',
    ]);

    stripeGatewayWith($fake)->charge($payment);

    expect($fake->requests)->toHaveCount(1)
        ->and((int) $fake->requests[0]['params']['amount'])->toBe(123_456)
        ->and($fake->requests[0]['params']['currency'])->toBe('jpy');
});

it('sends a stable idempotency key derived from the payment id', function () {
    $payment = Payment::factory()->create(['id' => 4242, 'amount' => 1_000, 'currency' => 'JPY', 'gateway' => 'stripe']);

    $fake = new FakeStripeHttpClient(200, [
        'id' => 'pi_test_idem',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'secret',
        'amount' => 1_000,
        'currency' => 'jpy',
    ]);

    stripeGatewayWith($fake)->charge($payment);

    expect($fake->requests[0]['headers'])->toContain('Idempotency-Key: checkout_payment_4242_create');
});

it('reports failure without throwing when Stripe returns an API error, and touches nothing on the payment row', function () {
    $payment = Payment::factory()->create(['amount' => 5_000, 'currency' => 'JPY', 'gateway' => 'stripe']);

    $fake = new FakeStripeHttpClient(402, [
        'error' => [
            'type' => 'card_error',
            'code' => 'card_declined',
            'message' => 'Your card was declined.',
        ],
    ]);

    $result = stripeGatewayWith($fake)->charge($payment);

    expect($result->successful)->toBeFalse()
        ->and($result->gatewayReference)->toBeNull();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_reference)->toBeNull()
        ->and($payment->raw_response)->toBeNull();
});

it('reports its own name as "stripe"', function () {
    $client = new StripeClient('sk_test_fake');

    expect((new StripePaymentGateway($client))->name())->toBe('stripe');
});
