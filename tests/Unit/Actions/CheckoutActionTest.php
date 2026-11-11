<?php

use App\Actions\CheckoutAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CheckoutTotalChangedException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\ShippingAddressNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\ShippingWeightBracket;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Notifications\PaymentConfirmedAdminNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use App\Services\DhlRateCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Builds a request all the way through the real quoted-and-selected state
 * (PresentQuoteAction -> SelectQuoteAction), the same fidelity as
 * SelectQuoteActionTest, plus a saved address for that same buyer -- ready
 * for CheckoutAction. Pins a single, known shipping_weight_brackets row so
 * the resulting shipping_fee is deterministic regardless of the
 * migration's own default-seeded brackets.
 *
 * @return array{0: PartRequest, 1: BuyerAddress}
 */
function checkoutReadyRequest(int $costPrice = 45_000): array
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

/**
 * A gateway shaped like Stripe: charge() succeeds (the PaymentIntent was
 * created) but never confirms the payment itself -- only a later webhook
 * does that.
 */
function fakeDeferredConfirmationGateway(): PaymentGateway
{
    return new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentResult
        {
            $payment->update(['gateway_reference' => 'pi_deferred_test']);

            return PaymentResult::success('pi_deferred_test');
        }

        public function name(): string
        {
            return 'fake-deferred';
        }
    };
}

function fakeFailingGateway(): PaymentGateway
{
    return new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentResult
        {
            return PaymentResult::failure(['declined' => true]);
        }

        public function name(): string
        {
            return 'fake-failing';
        }
    };
}

it('checks out a quoted request: confirms payment, snapshots the address, and moves the request to paid', function () {
    [$request, $address] = checkoutReadyRequest();

    $result = app(CheckoutAction::class)->execute($request, $address);

    expect($result->status)->toBe(RequestStatus::Paid)
        ->and($result->shipping_method)->toBe(ShippingMethod::Standard)
        ->and($result->shipping_fee)->toBe(8_000)
        ->and($result->shipping_address_id)->toBe($address->id)
        ->and($result->shipping_city)->toBe($address->city);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->amount)->toBe($result->buyer_price + 8_000)
        ->and($payment->currency)->toBe('JPY')
        ->and($payment->gateway)->toBe('stub')
        ->and($payment->paid_at)->not->toBeNull();
});

it('notifies the buyer and admins once the stub gateway confirms the payment', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    [$request, $address] = checkoutReadyRequest();

    app(CheckoutAction::class)->execute($request, $address);

    $buyer = $request->fresh()->buyer;
    Notification::assertSentToTimes($buyer->user, PaymentConfirmedNotification::class, 1);
    Notification::assertSentToTimes($admin, PaymentConfirmedAdminNotification::class, 1);
});

it('never sends a payment-confirmed notification when the gateway declines the charge', function () {
    [$request, $address] = checkoutReadyRequest();
    app()->instance(PaymentGateway::class, fakeFailingGateway());

    // Faked only from here -- checkoutReadyRequest() itself legitimately
    // fires QuotePresentedNotification/QuoteSelectedNotification as part
    // of its own setup, which assertNothingSent() would otherwise also
    // (correctly, but irrelevantly here) catch.
    Notification::fake();

    $attempt = fn () => app(CheckoutAction::class)->execute($request, $address);

    expect($attempt)->toThrow(PaymentFailedException::class);

    Notification::assertNothingSent();
});

it('charges the shipping fee already fixed by SelectQuoteAction, not a live recalculation', function () {
    [$request, $address] = checkoutReadyRequest();

    // Changing brackets after selection must never change what's charged --
    // the fee was already snapshotted onto the request at selection time.
    ShippingWeightBracket::query()->update(['fee' => 99_000]);

    $result = app(CheckoutAction::class)->execute($request, $address);

    expect($result->shipping_fee)->toBe(8_000);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->amount)->toBe($result->buyer_price + 8_000);
});

it('refuses checkout when no quote has been selected yet', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $attempt = fn () => app(CheckoutAction::class)->execute($request, $address);

    expect($attempt)->toThrow(CheckoutNotAllowedException::class);
    expect(Payment::count())->toBe(0);
    expect($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

it('refuses checkout from any status other than quoted', function (RequestStatus $status) {
    $request = PartRequest::factory()->create(['status' => $status]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $attempt = fn () => app(CheckoutAction::class)->execute($request, $address);

    expect($attempt)->toThrow(CheckoutNotAllowedException::class);
    expect(Payment::count())->toBe(0);
})->with([
    'new' => RequestStatus::New,
    'vendor inquiry' => RequestStatus::VendorInquiry,
    'awaiting payment (a checkout already started)' => RequestStatus::AwaitingPayment,
    'already paid' => RequestStatus::Paid,
    'ordered to vendor' => RequestStatus::OrderedToVendor,
]);

it('refuses checkout when the request has no shipping fee set -- defense in depth', function () {
    // Status/selected_response_id forced directly, bypassing
    // SelectQuoteAction, specifically to exercise this guard in isolation
    // (SelectQuoteAction itself always sets shipping_fee alongside
    // selected_response_id, so this combination shouldn't occur in
    // practice).
    $request = PartRequest::factory()->create([
        'status' => RequestStatus::Quoted,
        'selected_response_id' => VendorResponse::factory()->create()->id,
        'buyer_price' => 54_000,
        'shipping_fee' => null,
    ]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $attempt = fn () => app(CheckoutAction::class)->execute($request, $address);

    expect($attempt)->toThrow(CheckoutNotAllowedException::class);
    expect(Payment::count())->toBe(0);
});

it('refuses an address that belongs to a different buyer, rolling back the whole checkout', function () {
    [$request] = checkoutReadyRequest();
    $othersAddress = BuyerAddress::factory()->create(); // a different buyer entirely

    $attempt = fn () => app(CheckoutAction::class)->execute($request, $othersAddress);

    expect($attempt)->toThrow(ShippingAddressNotAllowedException::class);
    expect(Payment::count())->toBe(0);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->shipping_address_id)->toBeNull();
});

it('rolls back the entire checkout -- no payment row, no status change -- when the gateway declines the charge', function () {
    [$request, $address] = checkoutReadyRequest();
    app()->instance(PaymentGateway::class, fakeFailingGateway());

    $attempt = fn () => app(CheckoutAction::class)->execute($request, $address);

    expect($attempt)->toThrow(PaymentFailedException::class);
    expect(Payment::count())->toBe(0);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->shipping_address_id)->toBeNull();
});

it('refuses checkout against a free (無償) request -- that belongs to ConfirmFreeOrderAction instead', function () {
    [$request, $address] = checkoutReadyRequest();
    $request->update(['is_free' => true]);

    $attempt = fn () => app(CheckoutAction::class)->execute($request->fresh(), $address);

    expect($attempt)->toThrow(CheckoutNotAllowedException::class);
    expect(Payment::count())->toBe(0);
});

// --- policy: isolation (CLAUDE.md 4, 9) -----------------------------------

it('lets only the owning buyer check out their own request', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();
    $buyerA = User::factory()->buyer()->create();
    $buyerB = User::factory()->buyer()->create();
    $profileA = BuyerProfile::factory()->for($buyerA)->create();
    $request = PartRequest::factory()->create(['buyer_id' => $profileA->id]);

    expect($buyerA->can('checkout', $request))->toBeTrue()
        ->and($buyerB->can('checkout', $request))->toBeFalse()
        ->and($admin->can('checkout', $request))->toBeFalse()
        ->and($vendor->can('checkout', $request))->toBeFalse();
});

// --- awaiting_payment: never "paid" before the payment is confirmed -------------

it('leaves the request awaiting_payment, not paid, when the gateway has not confirmed the charge yet', function () {
    app()->instance(PaymentGateway::class, fakeDeferredConfirmationGateway());
    [$request, $address] = checkoutReadyRequest();
    Notification::fake();

    $result = app(CheckoutAction::class)->execute($request, $address);

    $payment = Payment::where('part_request_id', $request->id)->sole();

    expect($result->status)->toBe(RequestStatus::AwaitingPayment)
        ->and($result->hasBeenPaid())->toBeFalse()
        ->and($result->paymentGateOpen())->toBeFalse()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->paid_at)->toBeNull();

    // Nothing is confirmed yet, so nobody is told it is.
    Notification::assertNothingSent();
});

it('refuses a second checkout while the first is still awaiting payment -- never a second PaymentIntent for one request', function () {
    app()->instance(PaymentGateway::class, fakeDeferredConfirmationGateway());
    [$request, $address] = checkoutReadyRequest();

    app(CheckoutAction::class)->execute($request, $address);

    expect(fn () => app(CheckoutAction::class)->execute($request->fresh(), $address))
        ->toThrow(CheckoutNotAllowedException::class);
    expect(Payment::where('part_request_id', $request->id)->count())->toBe(1);
});

it('refuses a concurrent second checkout that read the request before the first one committed -- never a second PaymentIntent', function () {
    app()->instance(PaymentGateway::class, fakeDeferredConfirmationGateway());
    [$request, $address] = checkoutReadyRequest();

    // Two requests load the same `quoted` request; the first checks out.
    $staleCopy = PartRequest::findOrFail($request->id);
    app(CheckoutAction::class)->execute($request, $address);

    // The second still holds its stale in-memory `quoted` copy -- exactly
    // what a near-simultaneous second "Proceed to payment" sees. It must
    // re-check the locked, current row and refuse.
    expect($staleCopy->status)->toBe(RequestStatus::Quoted);
    expect(fn () => app(CheckoutAction::class)->execute($staleCopy, $address))
        ->toThrow(CheckoutNotAllowedException::class);

    expect(Payment::where('part_request_id', $request->id)->count())->toBe(1);
});

// --- shipping method choice ---------------------------------------------------------

it('snapshots DHL and its fee when the buyer picks DHL, and charges part price + the DHL fee', function () {
    [$request, $address] = checkoutReadyRequest();

    $result = app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Dhl);

    expect($result->shipping_method)->toBe(ShippingMethod::Dhl)
        ->and($result->shipping_fee)->toBe(DhlRateCalculator::DUMMY_FEE);

    expect(Payment::where('part_request_id', $request->id)->sole()->amount)
        ->toBe($result->buyer_price + DhlRateCalculator::DUMMY_FEE);
});

it('settles the shipping method and fee BEFORE the gateway is charged -- the intent is created for the final total', function () {
    [$request, $address] = checkoutReadyRequest();

    $gateway = new class implements PaymentGateway
    {
        /** @var array<string, mixed>|null */
        public ?array $seenAtCharge = null;

        public function charge(Payment $payment): PaymentResult
        {
            $partRequest = $payment->partRequest()->first();
            $this->seenAtCharge = [
                'amount' => $payment->amount,
                'shipping_method' => $partRequest->shipping_method,
                'shipping_fee' => $partRequest->shipping_fee,
            ];
            $payment->update(['gateway_reference' => 'pi_order_test']);

            return PaymentResult::success('pi_order_test');
        }

        public function name(): string
        {
            return 'fake-deferred';
        }
    };
    app()->instance(PaymentGateway::class, $gateway);

    $result = app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Dhl);

    expect($gateway->seenAtCharge)->toBe([
        'amount' => $result->buyer_price + DhlRateCalculator::DUMMY_FEE,
        'shipping_method' => ShippingMethod::Dhl,
        'shipping_fee' => DhlRateCalculator::DUMMY_FEE,
    ]);
});

it('refuses to charge when the total no longer matches what the buyer was shown -- nothing changes', function () {
    [$request, $address] = checkoutReadyRequest();
    $regularTotal = $request->buyer_price + 8_000;

    // The buyer saw the Regular total, but DHL is what reached the server.
    $attempt = fn () => app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Dhl, $regularTotal);

    expect($attempt)->toThrow(CheckoutTotalChangedException::class);

    $fresh = $request->fresh();
    expect(Payment::where('part_request_id', $request->id)->count())->toBe(0)
        ->and($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->shipping_method)->toBe(ShippingMethod::Standard)
        ->and($fresh->shipping_fee)->toBe(8_000)
        ->and($fresh->shipping_address_id)->toBeNull();
});

it('charges when the total matches what the buyer was shown', function (ShippingMethod $method, int $fee) {
    [$request, $address] = checkoutReadyRequest();

    app(CheckoutAction::class)->execute($request, $address, $method, $request->buyer_price + $fee);

    expect(Payment::where('part_request_id', $request->id)->sole()->amount)->toBe($request->buyer_price + $fee);
})->with([
    'regular' => [ShippingMethod::Standard, 8_000],
    'dhl' => [ShippingMethod::Dhl, DhlRateCalculator::DUMMY_FEE],
]);

it('locks the shipping method once the intent exists -- a later checkout with another method is refused and changes nothing', function () {
    app()->instance(PaymentGateway::class, fakeDeferredConfirmationGateway());
    [$request, $address] = checkoutReadyRequest();

    app(CheckoutAction::class)->execute($request, $address, ShippingMethod::Dhl);

    expect(fn () => app(CheckoutAction::class)->execute($request->fresh(), $address, ShippingMethod::Standard))
        ->toThrow(CheckoutNotAllowedException::class);

    $fresh = $request->fresh();
    expect($fresh->shipping_method)->toBe(ShippingMethod::Dhl)
        ->and($fresh->shipping_fee)->toBe(DhlRateCalculator::DUMMY_FEE)
        ->and(Payment::where('part_request_id', $request->id)->sole()->amount)->toBe($fresh->buyer_price + DhlRateCalculator::DUMMY_FEE);
});

it('Regular charges the admin\'s overridden fee from the presented quote, not the weight bracket', function () {
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => VendorProfile::factory()->create()->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);
    $quote = app(PresentQuoteAction::class)->execute($request, $response, 5_500, 'Consolidated with another shipment');
    app(SelectQuoteAction::class)->execute($request->fresh(), $quote);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $result = app(CheckoutAction::class)->execute($request->fresh(), $address, ShippingMethod::Standard);

    expect($result->shipping_fee)->toBe(5_500);
});
