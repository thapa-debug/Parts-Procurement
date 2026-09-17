<?php

use App\Actions\CheckoutAction;
use App\Actions\ConfirmFreeOrderAction;
use App\Actions\ConfirmOrderToVendorAction;
use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Carries a request all the way through the real flow -- present, select,
 * and check out (CheckoutAction, via the stub gateway which always
 * succeeds) -- so it lands genuinely `paid` with a real confirmed Payment
 * row, the same fidelity CheckoutActionTest itself uses.
 *
 * @return array{0: PartRequest, 1: int} request and the vendor id it should confirm to
 */
function paidRequestViaCheckout(int $costPrice = 45_000): array
{
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
    app(CheckoutAction::class)->execute($request->fresh(), $address);

    return [$request->fresh(), $vendor->id];
}

it('confirms the order and transitions to ordered_to_vendor once payment is confirmed', function () {
    [$request, $vendorId] = paidRequestViaCheckout();

    $result = app(ConfirmOrderToVendorAction::class)->execute($request);

    expect($result->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($result->confirmed_vendor_id)->toBe($vendorId);
});

it('opens the payment gate for a free (無償) order exactly the same way, via its ¥0 confirmed payment', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);
    app(ConfirmFreeOrderAction::class)->execute($request->fresh(), $address);

    $result = app(ConfirmOrderToVendorAction::class)->execute($request->fresh());

    expect($result->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($result->confirmed_vendor_id)->toBe($vendor->id);
});

it('refuses to confirm a vendor purchase on an unpaid request -- CLAUDE.md §6.3\'s payment gate', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request);

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
    expect($request->fresh()->status)->toBe(RequestStatus::Quoted)
        ->and($request->fresh()->confirmed_vendor_id)->toBeNull();
});

it('refuses to confirm when a payment exists but is only pending, not confirmed', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Paid]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Pending]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request);

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
    expect($request->fresh()->status)->toBe(RequestStatus::Paid);
});

it('refuses to confirm when the request is marked paid but has no payment row at all', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Paid]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request);

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
});

it('refuses to confirm when a confirmed payment exists but the request never actually reached paid', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Confirmed]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request);

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
});

// --- policy: isolation (CLAUDE.md 4, 9) -----------------------------------

it('lets only the admin confirm an order to vendor', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();
    $request = PartRequest::factory()->create();

    expect($admin->can('confirmOrderToVendor', $request))->toBeTrue()
        ->and($buyer->can('confirmOrderToVendor', $request))->toBeFalse()
        ->and($vendor->can('confirmOrderToVendor', $request))->toBeFalse();
});
