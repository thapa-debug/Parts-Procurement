<?php

use App\Actions\CheckoutAction;
use App\Actions\ConfirmOrderToVendorAction;
use App\Actions\ConfirmStripePaymentAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\ShippingWeightBracket;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * End-to-end proof of the whole stripe integration's design (CLAUDE.md §14
 * Phase 4 stripe integration): CheckoutAction itself is completely
 * unmodified -- swapping the bound PaymentGateway is the only thing that
 * changes -- yet the money-critical gate (§6.3) only ever opens once a
 * *webhook* (never the checkout request itself) confirms the payment.
 */
class FakeStripeHttpClientForCheckoutFlow implements ClientInterface
{
    public function __construct(private readonly array $responseBody) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        return [json_encode($this->responseBody), 200, []];
    }
}

/**
 * @return array{0: PartRequest, 1: BuyerAddress}
 */
function stripeCheckoutReadyRequest(int $costPrice = 45_000): array
{
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => $costPrice,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    return [$request->fresh(), $address];
}

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('opens the payment gate only after the webhook confirms -- never at checkout time', function () {
    [$request, $address] = stripeCheckoutReadyRequest();

    ApiRequestor::setHttpClient(new FakeStripeHttpClientForCheckoutFlow([
        'id' => 'pi_e2e_test',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_e2e_test_secret',
    ]));
    app()->instance(PaymentGateway::class, new StripePaymentGateway(new StripeClient('sk_test_fake')));

    // Step 1: checkout runs, completely unmodified from the stub-gateway
    // path. It succeeds (the PaymentIntent was created) and the request
    // reads `paid` -- but nothing has actually been charged yet.
    $result = app(CheckoutAction::class)->execute($request, $address);

    expect($result->status)->toBe(RequestStatus::Paid);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway)->toBe('stripe')
        ->and($payment->gateway_reference)->toBe('pi_e2e_test');

    // Step 2: the buyer never actually paid -- no webhook has arrived.
    // The admin must not be able to confirm the vendor purchase.
    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($result);
    expect($attempt)->toThrow(PaymentNotConfirmedException::class);

    // Step 3: Stripe's webhook confirms the PaymentIntent actually
    // succeeded (ConfirmStripePaymentAction -- never anything reachable
    // from the browser).
    app(ConfirmStripePaymentAction::class)->execute('pi_e2e_test');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed);

    // Step 4: only now does the gate open.
    $confirmed = app(ConfirmOrderToVendorAction::class)->execute($result->fresh());

    expect($confirmed->status)->toBe(RequestStatus::OrderedToVendor);
});
