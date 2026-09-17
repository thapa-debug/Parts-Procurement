<?php

use App\Actions\ConfirmFreeOrderAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\FreeOrderNotAllowedException;
use App\Exceptions\ShippingAddressNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Builds a free (無償) request all the way through the real
 * quoted-and-selected state (PresentQuoteAction with isFree: true ->
 * SelectQuoteAction), the same fidelity as CheckoutActionTest's own
 * checkoutReadyRequest(), plus a saved address for that same buyer -- ready
 * for ConfirmFreeOrderAction.
 *
 * @return array{0: PartRequest, 1: BuyerAddress}
 */
function freeOrderReadyRequest(int $costPrice = 45_000): array
{
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => $costPrice,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    return [$request->fresh(), $address];
}

it('confirms a free order: snapshots the address, records a ¥0 confirmed payment, and moves the request to paid', function () {
    [$request, $address] = freeOrderReadyRequest();

    $result = app(ConfirmFreeOrderAction::class)->execute($request, $address);

    expect($result->status)->toBe(RequestStatus::Paid)
        ->and($result->shipping_address_id)->toBe($address->id)
        ->and($result->shipping_city)->toBe($address->city);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->amount)->toBe(0)
        ->and($payment->currency)->toBe('JPY')
        ->and($payment->gateway)->toBe('waived')
        ->and($payment->paid_at)->not->toBeNull();
});

it('refuses to confirm a request that is not free -- that belongs to CheckoutAction instead', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);
    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);
    $request = $request->fresh();
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $attempt = fn () => app(ConfirmFreeOrderAction::class)->execute($request, $address);

    expect($attempt)->toThrow(FreeOrderNotAllowedException::class);
    expect(Payment::count())->toBe(0);
    expect($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

it('refuses to confirm a free request with no quote selected yet', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted, 'is_free' => true]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    $attempt = fn () => app(ConfirmFreeOrderAction::class)->execute($request, $address);

    expect($attempt)->toThrow(FreeOrderNotAllowedException::class);
    expect(Payment::count())->toBe(0);
});

it('refuses an address that belongs to a different buyer, rolling back the whole confirmation', function () {
    [$request] = freeOrderReadyRequest();
    $othersAddress = BuyerAddress::factory()->create(); // a different buyer entirely

    $attempt = fn () => app(ConfirmFreeOrderAction::class)->execute($request, $othersAddress);

    expect($attempt)->toThrow(ShippingAddressNotAllowedException::class);
    expect(Payment::count())->toBe(0);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->shipping_address_id)->toBeNull();
});

it('rolls back the whole confirmation -- no payment row, no status change -- if the transaction fails', function () {
    [$request, $address] = freeOrderReadyRequest();

    PartRequest::updating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $attempt = fn () => app(ConfirmFreeOrderAction::class)->execute($request, $address);

    expect($attempt)->toThrow(RuntimeException::class);
    expect(Payment::count())->toBe(0);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->shipping_address_id)->toBeNull();
});

// --- policy: isolation (CLAUDE.md 4, 9) -----------------------------------

it('lets only the owning buyer confirm their own free request', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();
    $buyerA = User::factory()->buyer()->create();
    $buyerB = User::factory()->buyer()->create();
    $profileA = BuyerProfile::factory()->for($buyerA)->create();
    $request = PartRequest::factory()->create(['buyer_id' => $profileA->id, 'is_free' => true]);

    expect($buyerA->can('confirmFreeOrder', $request))->toBeTrue()
        ->and($buyerB->can('confirmFreeOrder', $request))->toBeFalse()
        ->and($admin->can('confirmFreeOrder', $request))->toBeFalse()
        ->and($vendor->can('confirmFreeOrder', $request))->toBeFalse();
});
