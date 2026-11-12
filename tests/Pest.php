<?php

use App\Actions\ConfirmOrderToVendorAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;
use Tests\TestCase;

uses(TestCase::class)
    // Tests must never reach the real Stripe API (CLAUDE.md §9): every test
    // starts with a fake transport that answers nothing, so any Stripe call
    // a test hasn't explicitly faked (fakeStripeRoutes() or its own fake)
    // fails loudly instead of going over the network.
    ->beforeEach(fn () => ApiRequestor::setHttpClient(new RoutedFakeStripeHttpClient([])))
    ->in('Feature', 'Unit');

// --- shared helpers ------------------------------------------------------------

// Shared by the vendor RequestResponse and Inbox tests -- the confirmed-order
// setup both screens' isolation tests build on.
/**
 * Two invited vendors, both responded; $confirmed's response was selected,
 * paid for, and confirmed to the Chiba yard through the real Action. The
 * buyer has a distinctive company name and shipping address that must never
 * reach either vendor.
 *
 * @return array{0: PartRequest, 1: User, 2: User}
 */
function confirmedOrderWithTwoVendors(DeliveryMethod $deliveryMethod = DeliveryMethod::Deliver): array
{
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Secret Buyer Co']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create([
        'status' => RequestStatus::Paid,
        'shipping_recipient_name' => 'Buyer Recipient Person',
        'shipping_address_line1' => '42 Buyer Street',
    ]);

    $confirmedUser = User::factory()->vendor()->create();
    $confirmed = VendorProfile::factory()->for($confirmedUser)->create(['delivery_method' => $deliveryMethod]);
    $otherUser = User::factory()->vendor()->create();
    $other = VendorProfile::factory()->for($otherUser)->create();

    foreach ([$confirmed, $other] as $vendor) {
        $request->vendors()->attach($vendor->id, ['invited_at' => now()]);
    }

    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $confirmed->id]);
    VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $other->id]);
    $request->update(['selected_response_id' => $response->id]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Confirmed]);

    $address = CompanyAddress::factory()->create([
        'label' => 'Chiba yard',
        'recipient_name' => 'Receiving desk',
        'phone' => '043-123-4567',
        'postal_code' => '260-0001',
        'prefecture' => 'Chiba',
        'city' => 'Chiba-shi',
        'address_line1' => '1-2-3 Chuo',
    ]);

    app(ConfirmOrderToVendorAction::class)->execute($request->fresh(), $address);

    return [$request->fresh(), $confirmedUser, $otherUser];
}

// Shared by the admin RequestBoard and RequestDetail tests -- the
// "quotes received" chip on a vendor_inquiry request.
/**
 * A request broadcast to $invited vendors, of whom $quotes sent a real
 * quote and $noStock replied "no stock" (the rest haven't replied yet).
 *
 * @param  array<string, mixed>  $attributes
 */
function inquiryWithResponses(int $invited, int $quotes, int $noStock, array $attributes = []): PartRequest
{
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry, ...$attributes]);
    $vendors = VendorProfile::factory()->count($invited)->create();

    foreach ($vendors as $i => $vendor) {
        $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

        if ($i < $quotes) {
            VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id]);
        } elseif ($i < $quotes + $noStock) {
            VendorResponse::factory()->noStock()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id]);
        }
    }

    return $request;
}

// Shared by the Stripe reconcile / cancel-checkout tests.
/**
 * A fake Stripe HTTP transport (the real stripe-php SDK still runs) that
 * answers ONLY the calls a test lists, keyed "<method> <path>" -- e.g.
 * 'get /v1/payment_intents/pi_x'. Any other call throws, so a test proves
 * there was no stray cancel or create. A route's value is the JSON body, a
 * closure returning it (run at call time), or ['__status' => 4xx, ...body]
 * for an error response. Every call is recorded in $calls.
 */
class RoutedFakeStripeHttpClient implements ClientInterface
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * @param  array<string, array<string, mixed>|Closure>  $routes
     */
    public function __construct(private readonly array $routes) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $key = strtolower($method).' '.parse_url($absUrl, PHP_URL_PATH);
        $this->calls[] = $key;

        if (! array_key_exists($key, $this->routes)) {
            throw new RuntimeException("Unexpected Stripe call: {$key}");
        }

        $body = $this->routes[$key] instanceof Closure ? ($this->routes[$key])() : $this->routes[$key];
        $status = $body['__status'] ?? 200;
        unset($body['__status']);

        return [json_encode($body), $status, []];
    }
}

/**
 * @param  array<string, array<string, mixed>|Closure>  $routes
 */
function fakeStripeRoutes(array $routes): RoutedFakeStripeHttpClient
{
    $http = new RoutedFakeStripeHttpClient($routes);
    ApiRequestor::setHttpClient($http);
    app()->instance(StripeClient::class, new StripeClient('sk_test_fake'));

    return $http;
}

/**
 * @return array<string, mixed> a PaymentIntent JSON body
 */
function stripeIntent(string $id, string $status, int $amount = 62_000, string $currency = 'jpy'): array
{
    return ['id' => $id, 'object' => 'payment_intent', 'status' => $status, 'amount' => $amount, 'currency' => $currency, 'client_secret' => "{$id}_secret"];
}

/**
 * An abandoned Stripe checkout: the request is awaiting_payment, its one
 * Stripe payment (¥62,000) points at PaymentIntent $intentId.
 *
 * @return array{0: PartRequest, 1: Payment}
 */
function awaitingStripePayment(string $intentId = 'pi_stuck', PaymentStatus $paymentStatus = PaymentStatus::Pending): array
{
    $request = PartRequest::factory()->create([
        'status' => RequestStatus::AwaitingPayment,
        'buyer_price' => 54_000,
        'shipping_fee' => 8_000,
        'is_free' => false,
    ]);
    $payment = Payment::factory()->create([
        'part_request_id' => $request->id,
        'amount' => 62_000,
        'currency' => 'JPY',
        'gateway' => 'stripe',
        'gateway_reference' => $intentId,
        'status' => $paymentStatus,
    ]);

    return [$request, $payment];
}
