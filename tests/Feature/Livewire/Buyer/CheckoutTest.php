<?php

use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Livewire\Buyer\Checkout;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: BuyerProfile, 2: PartRequest}
 */
function checkoutEligibleRequest(int $costPrice = 45_000): array
{
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($profile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    $vendor = VendorProfile::factory()->create(['company_name' => 'Secret Vendor Co']);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => $costPrice,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    return [$owner, $profile, $request->fresh()];
}

it('shows a not-eligible message when no quote has been selected yet', function () {
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($profile, 'buyer')->create(['status' => RequestStatus::Quoted]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSee(__('buyer.checkout.not_eligible'))
        ->assertDontSee(__('buyer.checkout.pay_button'));
});

it('shows a not-eligible message once the request has already been paid', function () {
    [$owner, , $request] = checkoutEligibleRequest();
    $request->update(['status' => RequestStatus::Paid]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSee(__('buyer.checkout.not_eligible'));
});

it('pre-selects the buyer\'s default address and shows the fee breakdown when eligible', function () {
    Setting::set('shipping_fee_vehicle', 8_000, 'integer');
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $default = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true, 'recipient_name' => 'Default Recipient']);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', $default->id)
        ->assertSee('Default Recipient')
        ->assertSee('54,000') // buyer_price
        ->assertSee('8,000'); // vehicle fee
});

it('never sends the vendor\'s identity or the request\'s cost fields to the browser', function () {
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    $component = Livewire::actingAs($owner)->test(Checkout::class, ['partRequest' => $request]);

    $snapshotJson = json_encode($component->snapshot);

    expect($snapshotJson)->not->toContain('Secret Vendor Co')
        ->and($snapshotJson)->not->toContain('cost_price')
        ->and($snapshotJson)->not->toContain('selected_response_id')
        ->and($snapshotJson)->not->toContain('45000')
        ->and($snapshotJson)->toContain('partRequestId');

    $component->assertDontSee('Secret Vendor Co')->assertDontSee('45,000');
});

it('never offers dhl as a shipping method', function () {
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertDontSee('value="dhl"', false);
});

it('pays successfully: confirms the payment, snapshots the address, and redirects', function () {
    Setting::set('shipping_fee_vehicle', 8_000, 'integer');
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->set('shippingMethod', 'vehicle')
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect(route('buyer.requests.show', $request->id));

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Paid)
        ->and($fresh->shipping_address_id)->toBe($address->id);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->amount)->toBe(54_000 + 8_000);
});

it('charges the container fee, not the vehicle fee, when container is chosen', function () {
    Setting::set('shipping_fee_vehicle', 8_000, 'integer');
    Setting::set('shipping_fee_container', 25_000, 'integer');
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->set('shippingMethod', 'container')
        ->call('pay')
        ->assertHasNoErrors();

    expect($request->fresh()->shipping_fee)->toBe(25_000);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->amount)->toBe(54_000 + 25_000);
});

it('rejects paying with an address that belongs to a different buyer', function () {
    [$owner, , $request] = checkoutEligibleRequest();
    $othersAddress = BuyerAddress::factory()->create(); // a different buyer entirely

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->set('selectedAddressId', $othersAddress->id)
        ->set('shippingMethod', 'vehicle')
        ->call('pay')
        ->assertHasErrors(['selectedAddressId']);

    expect(Payment::count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

it('adds a new address inline and auto-selects it', function () {
    [$owner, , $request] = checkoutEligibleRequest();
    $country = Country::factory()->create();

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('toggleNewAddressForm')
        ->set('recipient_name', 'Inline Recipient')
        ->set('phone', '080-0000-0000')
        ->set('postal_code', '100-0001')
        ->set('country_id', (string) $country->id)
        ->set('city', 'Chiyoda')
        ->set('address_line1', '1-1 Marunouchi')
        ->call('addAddress')
        ->assertHasNoErrors();

    $address = BuyerAddress::where('recipient_name', 'Inline Recipient')->sole();
    expect($address->buyer_id)->toBe(BuyerProfile::where('user_id', $owner->id)->value('id'));
});
