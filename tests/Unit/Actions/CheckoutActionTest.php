<?php

use App\Actions\CheckoutAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
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
use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
