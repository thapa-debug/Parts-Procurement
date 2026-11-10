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
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

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

    $result = app(ConfirmOrderToVendorAction::class)->execute($request, CompanyAddress::factory()->create());

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

    $result = app(ConfirmOrderToVendorAction::class)->execute($request->fresh(), CompanyAddress::factory()->create());

    expect($result->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($result->confirmed_vendor_id)->toBe($vendor->id);
});

it('refuses to confirm a vendor purchase on an unpaid request -- CLAUDE.md §6.3\'s payment gate', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request, CompanyAddress::factory()->create());

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
    expect($request->fresh()->status)->toBe(RequestStatus::Quoted)
        ->and($request->fresh()->confirmed_vendor_id)->toBeNull()
        ->and($request->fresh()->receiving_address_id)->toBeNull()
        ->and($request->fresh()->receiving_label)->toBeNull();
});

it('refuses to confirm when a payment exists but is only pending, not confirmed', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Paid]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Pending]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request, CompanyAddress::factory()->create());

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
    expect($request->fresh()->status)->toBe(RequestStatus::Paid);
});

it('refuses to confirm when the request is marked paid but has no payment row at all', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Paid]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request, CompanyAddress::factory()->create());

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
});

it('refuses to confirm when a confirmed payment exists but the request never actually reached paid', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Confirmed]);

    $attempt = fn () => app(ConfirmOrderToVendorAction::class)->execute($request, CompanyAddress::factory()->create());

    expect($attempt)->toThrow(PaymentNotConfirmedException::class);
});

// --- company receiving address snapshot (CLAUDE.md §14 Phase 4 slice 6) ---

it('snapshots the chosen company receiving address onto the order', function () {
    [$request] = paidRequestViaCheckout();
    $address = CompanyAddress::factory()->create([
        'label' => 'Chiba yard',
        'recipient_name' => 'Receiving desk',
        'phone' => '043-123-4567',
        'postal_code' => '260-0001',
        'prefecture' => 'Chiba',
        'city' => 'Chiba-shi',
        'address_line1' => '1-2-3 Chuo',
        'address_line2' => 'Gate B',
    ]);

    $result = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    expect($result->receiving_address_id)->toBe($address->id)
        ->and($result->receiving_label)->toBe('Chiba yard')
        ->and($result->receiving_recipient_name)->toBe('Receiving desk')
        ->and($result->receiving_phone)->toBe('043-123-4567')
        ->and($result->receiving_postal_code)->toBe('260-0001')
        ->and($result->receiving_prefecture)->toBe('Chiba')
        ->and($result->receiving_city)->toBe('Chiba-shi')
        ->and($result->receiving_address_line1)->toBe('1-2-3 Chuo')
        ->and($result->receiving_address_line2)->toBe('Gate B');
});

it('keeps the snapshotted receiving address after the company address is edited', function () {
    [$request] = paidRequestViaCheckout();
    $address = CompanyAddress::factory()->create(['label' => 'Chiba yard', 'address_line1' => '1-2-3 Chuo']);

    app(ConfirmOrderToVendorAction::class)->execute($request, $address);
    $address->update(['label' => 'Renamed yard', 'address_line1' => '9-9-9 Elsewhere']);

    expect($request->fresh()->receiving_label)->toBe('Chiba yard')
        ->and($request->fresh()->receiving_address_line1)->toBe('1-2-3 Chuo');
});

it('keeps the snapshotted receiving address after the company address is deleted, clearing only the fk', function () {
    [$request] = paidRequestViaCheckout();
    $address = CompanyAddress::factory()->create(['label' => 'Chiba yard']);

    app(ConfirmOrderToVendorAction::class)->execute($request, $address);
    $address->delete();

    expect($request->fresh()->receiving_address_id)->toBeNull()
        ->and($request->fresh()->receiving_label)->toBe('Chiba yard');
});

it('records the order confirmation in the activity log', function () {
    [$request, $vendorId] = paidRequestViaCheckout();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $address = CompanyAddress::factory()->create(['label' => 'Chiba yard']);

    app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    $activity = Activity::query()->where('subject_type', PartRequest::class)->where('subject_id', $request->id)->sole();

    expect($activity->event)->toBe('order_confirmed_to_vendor')
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['confirmed_vendor_id'])->toBe($vendorId)
        ->and($activity->properties['receiving_address_id'])->toBe($address->id)
        ->and($activity->properties['receiving_label'])->toBe('Chiba yard');
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
