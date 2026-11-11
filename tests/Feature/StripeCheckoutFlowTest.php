<?php

use App\Actions\CheckoutAction;
use App\Actions\ConfirmOrderToVendorAction;
use App\Actions\ConfirmStripePaymentAction;
use App\Actions\PresentQuoteAction;
use App\Actions\ResumeStripePaymentAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Models\BuyerAddress;
use App\Models\CompanyAddress;
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

    // Proceeding to payment only created the PaymentIntent -- the request
    // reads awaiting_payment, never paid, until Stripe confirms.
    expect($result->status)->toBe(RequestStatus::AwaitingPayment);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway)->toBe('stripe')
        ->and($payment->gateway_reference)->toBe('pi_e2e_test');

    // Step 2: the buyer never actually paid -- no webhook has arrived.
    // The admin must not be able to confirm the vendor purchase.
    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($result, CompanyAddress::factory()->create());
    expect($attempt)->toThrow(PaymentNotConfirmedException::class);

    // Step 3: Stripe's webhook confirms the PaymentIntent actually
    // succeeded (ConfirmStripePaymentAction -- never anything reachable
    // from the browser).
    app(ConfirmStripePaymentAction::class)->execute('pi_e2e_test');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($result->fresh()->status)->toBe(RequestStatus::Paid);

    // Step 4: only now does the gate open.
    $confirmed = app(ConfirmOrderToVendorAction::class)->execute($result->fresh(), CompanyAddress::factory()->create());

    expect($confirmed->status)->toBe(RequestStatus::OrderedToVendor);
});

// --- abandoned checkout, resumed (one chargeable intent per request) ---------------

/**
 * Answers Stripe calls from a queue (repeating the last response) and
 * records each as "<method> <path>", so a test can prove how many
 * PaymentIntents were ever created, and in what order.
 */
class QueuedRecordingStripeHttpClientForFlow implements ClientInterface
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array> */
    private array $bodies;

    public function __construct(array ...$bodies)
    {
        $this->bodies = $bodies;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->calls[] = strtolower($method).' '.parse_url($absUrl, PHP_URL_PATH);
        $body = count($this->bodies) > 1 ? array_shift($this->bodies) : $this->bodies[0];

        return [json_encode($body), 200, []];
    }
}

function intentBody(string $id, string $status, int $amount = 62_000): array
{
    return ['id' => $id, 'object' => 'payment_intent', 'status' => $status, 'amount' => $amount, 'currency' => 'jpy', 'client_secret' => "{$id}_secret"];
}

function useFakeStripe(QueuedRecordingStripeHttpClientForFlow $http): void
{
    ApiRequestor::setHttpClient($http);
    $client = new StripeClient('sk_test_fake');
    app()->instance(StripeClient::class, $client);
    app()->instance(PaymentGateway::class, new StripePaymentGateway($client));
}

it('completes an abandoned checkout on the SAME PaymentIntent: resume, webhook, paid, gate opens', function () {
    [$request, $address] = stripeCheckoutReadyRequest(); // 54,000 + 8,000 shipping = 62,000
    $stripe = new QueuedRecordingStripeHttpClientForFlow(
        intentBody('pi_first', 'requires_payment_method'), // checkout: create
        intentBody('pi_first', 'requires_payment_method'), // resume: retrieve
    );
    useFakeStripe($stripe);

    // The buyer proceeds to payment, then abandons the card form.
    $request = app(CheckoutAction::class)->execute($request, $address);
    expect($request->status)->toBe(RequestStatus::AwaitingPayment);

    // They come back: resume hands back the same intent's client secret.
    expect(app(ResumeStripePaymentAction::class)->execute($request))->toBe('pi_first_secret')
        ->and($stripe->calls)->toBe(['post /v1/payment_intents', 'get /v1/payment_intents/pi_first']);

    // Still nothing confirmed -- the gate stays shut until the webhook.
    expect(fn () => app(ConfirmOrderToVendorAction::class)->execute($request->fresh(), CompanyAddress::factory()->create()))
        ->toThrow(PaymentNotConfirmedException::class);

    // They pay; Stripe's webhook confirms the one intent.
    app(ConfirmStripePaymentAction::class)->execute('pi_first');

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->gateway_reference)->toBe('pi_first')
        ->and($payment->amount)->toBe(62_000)
        ->and($request->fresh()->status)->toBe(RequestStatus::Paid);

    $confirmed = app(ConfirmOrderToVendorAction::class)->execute($request->fresh(), CompanyAddress::factory()->create());
    expect($confirmed->status)->toBe(RequestStatus::OrderedToVendor);
});

it('replaces a dead intent only after it is canceled, then completes on the replacement: webhook, paid, gate opens', function () {
    [$request, $address] = stripeCheckoutReadyRequest();
    $stripe = new QueuedRecordingStripeHttpClientForFlow(
        intentBody('pi_old', 'requires_payment_method'), // checkout: create
        intentBody('pi_old', 'canceled'),                // resume: retrieve -> dead at Stripe
        intentBody('pi_new', 'requires_payment_method'), // resume: create the one replacement
    );
    useFakeStripe($stripe);

    $request = app(CheckoutAction::class)->execute($request, $address);

    expect(app(ResumeStripePaymentAction::class)->execute($request))->toBe('pi_new_secret')
        // Exactly two intents ever, and the second only once the first was
        // seen canceled -- never two chargeable at the same time.
        ->and($stripe->calls)->toBe([
            'post /v1/payment_intents',
            'get /v1/payment_intents/pi_old',
            'post /v1/payment_intents',
        ]);

    app(ConfirmStripePaymentAction::class)->execute('pi_new');

    $payments = Payment::where('part_request_id', $request->id)->orderBy('id')->get();
    expect($payments->pluck('status', 'gateway_reference')->all())
        ->toBe(['pi_old' => PaymentStatus::Failed, 'pi_new' => PaymentStatus::Confirmed])
        ->and($payments[1]->amount)->toBe(62_000)
        ->and($request->fresh()->status)->toBe(RequestStatus::Paid);

    $confirmed = app(ConfirmOrderToVendorAction::class)->execute($request->fresh(), CompanyAddress::factory()->create());
    expect($confirmed->status)->toBe(RequestStatus::OrderedToVendor);
});

it('cancels a still-live but unusable intent at Stripe before creating the replacement', function () {
    [$request, $address] = stripeCheckoutReadyRequest();
    $stripe = new QueuedRecordingStripeHttpClientForFlow(
        intentBody('pi_old', 'requires_payment_method'), // checkout: create
        intentBody('pi_old', 'requires_reauthorization'), // resume: retrieve -> not payable, not canceled
        intentBody('pi_old', 'canceled'),                // resume: cancel it first
        intentBody('pi_new', 'requires_payment_method'), // resume: then the replacement
    );
    useFakeStripe($stripe);

    $request = app(CheckoutAction::class)->execute($request, $address);
    app(ResumeStripePaymentAction::class)->execute($request);

    expect($stripe->calls)->toBe([
        'post /v1/payment_intents',
        'get /v1/payment_intents/pi_old',
        'post /v1/payment_intents/pi_old/cancel',
        'post /v1/payment_intents',
    ]);
});
