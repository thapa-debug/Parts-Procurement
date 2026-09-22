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
use App\Models\ShippingWeightBracket;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

uses(RefreshDatabase::class);

/**
 * Same HTTP-transport-swap technique as StripePaymentGatewayTest -- the
 * real stripe-php SDK code runs (both the PaymentIntent create() inside
 * StripePaymentGateway::charge() and the retrieve() Checkout::pay() does
 * afterward to hand the client secret to the browser), only the network
 * call is faked.
 */
class FakeStripeHttpClientForCheckoutTest implements ClientInterface
{
    public function __construct(private readonly array $responseBody) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        return [json_encode($this->responseBody), 200, []];
    }
}

/**
 * @return array{0: User, 1: BuyerProfile, 2: PartRequest}
 */
function checkoutEligibleRequest(int $costPrice = 45_000): array
{
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);

    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($profile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    $vendor = VendorProfile::factory()->create(['company_name' => 'Secret Vendor Co']);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => $costPrice,
        'weight_kg' => 12,
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
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $default = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true, 'recipient_name' => 'Default Recipient']);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', $default->id)
        ->assertSee('Default Recipient')
        ->assertSee('54,000') // buyer_price
        ->assertSee('8,000'); // shipping fee, already fixed at selection time
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

it('pays successfully: confirms the payment, snapshots the address, and redirects', function () {
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect(route('buyer.requests.show', $request->id))
        // A durable, server-rendered confirmation on the destination page
        // -- not just a toast a redirect could wipe before it's seen.
        ->assertSessionHas('status', __('buyer.checkout.paid', ['code' => $request->request_code]));

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Paid)
        ->and($fresh->shipping_address_id)->toBe($address->id);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->amount)->toBe(54_000 + 8_000);
});

it('charges the fee already fixed by SelectQuoteAction, unaffected by a later bracket change', function () {
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    ShippingWeightBracket::query()->update(['fee' => 99_000]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('pay')
        ->assertHasNoErrors();

    expect($request->fresh()->shipping_fee)->toBe(8_000);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->amount)->toBe(54_000 + 8_000);
});

it('rejects paying with an address that belongs to a different buyer', function () {
    [$owner, , $request] = checkoutEligibleRequest();
    $othersAddress = BuyerAddress::factory()->create(); // a different buyer entirely

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->set('selectedAddressId', $othersAddress->id)
        ->call('pay')
        ->assertHasErrors(['selectedAddressId']);

    expect(Payment::count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

// --- stripe gateway (CLAUDE.md §14 stripe integration) ---------------------

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('shows the Stripe Elements card form when the stripe gateway is active, not the plain pay button alone', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSee(__('buyer.checkout.card_section'))
        ->assertSee('js.stripe.com');
});

it('blocks the Stripe card step and shows an upfront message when the buyer has no addresses at all', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, , $request] = checkoutEligibleRequest();
    // Deliberately no BuyerAddress rows for this buyer.

    $component = Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', null)
        ->assertSee(__('buyer.checkout.address_required_for_payment'))
        ->assertDontSee(__('buyer.checkout.card_section_help'));

    // The Payment Element's mount point itself must not be in the DOM --
    // it must never be possible to reach card entry without an address.
    expect($component->html())->not->toContain('x-ref="paymentElement"');
});

it('reaches the card step (a "Proceed to payment" prompt, not the card form itself) immediately for a buyer who already has a default address', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    $component = Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSee(__('buyer.checkout.proceed_to_payment_button'))
        ->assertSee(__('buyer.checkout.proceed_to_payment_help'))
        ->assertDontSee(__('buyer.checkout.address_required_for_payment'));

    // The Payment Element's own real PaymentIntent doesn't exist yet --
    // no charge attempt happens just from loading this page. The card
    // form's markup (card_section_help/the mount <div>) IS present in
    // the server-rendered HTML at this point (Alpine's x-show, not a
    // Blade @if, controls its visibility -- see checkout.blade.php's own
    // comment on why $refs.paymentElement must always resolve), but it
    // stays hidden client-side until proceedToPayment() actually mounts
    // it, which Pest can't exercise without a real browser.
    expect($component->html())->toContain('x-ref="paymentElement"')
        ->toContain('x-show="cardMounted"');
});

it('reports a Stripe pay() validation failure through the return value, never the error bag, once the card form is mounted', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $ownAddress = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);
    $othersAddress = BuyerAddress::factory()->create(); // a different buyer entirely

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        // The card form is mounted for this state (an address is already
        // selected) -- an error bag entry here would trigger a normal
        // re-render and wipe it out from under the buyer, the exact bug
        // this fix closes.
        ->assertSet('selectedAddressId', $ownAddress->id)
        ->set('selectedAddressId', $othersAddress->id)
        ->call('pay')
        ->assertHasNoErrors()
        ->assertReturned(fn ($data) => is_array($data) && isset($data['error']));

    expect(Payment::count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

it('defers address-radio updates (no live round trip) once the Stripe card form is already mounted, protecting it from a wiping re-render', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $first = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);
    BuyerAddress::factory()->create(['buyer_id' => $profile->id]);

    $component = Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', $first->id) // card form is already mounted for this state
        ->assertSee(__('buyer.checkout.card_section_help'));

    $html = $component->html();
    expect($html)->toContain('wire:model="selectedAddressId"')
        ->and($html)->not->toContain('wire:model.live="selectedAddressId"')
        // A shared name= is what makes the browser itself enforce "only one
        // checked at a time" now that there's no live round trip doing it
        // via a fresh render on every click (see the Blade comment).
        ->and(substr_count($html, 'name="selectedAddressId"'))->toBe(2)
        // Switching addresses after the real PaymentIntent already exists
        // (cardMounted) would silently do nothing -- the shipping address
        // is snapshotted the moment proceedToPayment() succeeds -- so the
        // radios disable themselves at that point instead of pretending
        // the switch still works.
        ->and(substr_count($html, ':disabled="cardMounted"'))->toBe(2);
});

it('uses a live round trip for the address radios before any address is selected, so picking one actually reveals the card form', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    // No default address -- selectedAddressId starts null, nothing is
    // mounted yet, so a normal re-render on selection is still safe (and
    // is exactly what's needed to reveal the card form for the first time).
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => false]);

    $component = Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', null)
        ->assertSee(__('buyer.checkout.address_required_for_payment'));

    expect($component->html())->toContain('wire:model.live="selectedAddressId"');
});

it('lets a buyer switch to a different saved address after the card form is showing, and ships to the newly selected one', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    $default = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);
    $alternate = BuyerAddress::factory()->create(['buyer_id' => $profile->id]);

    ApiRequestor::setHttpClient(new FakeStripeHttpClientForCheckoutTest([
        'id' => 'pi_switch_test',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_switch_test_secret',
    ]));

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', $default->id) // card form mounted on the default
        // Simulates the deferred wire:model eventually syncing the
        // buyer's later click -- in the browser this never round-trips on
        // its own (see the two tests above), it travels along with the
        // next actual request instead, exactly like the real
        // $wire.pay() call below.
        ->set('selectedAddressId', $alternate->id)
        ->call('pay')
        ->assertHasNoErrors()
        ->assertReturned(fn ($data) => $data['clientSecret'] === 'pi_switch_test_secret');

    expect($request->fresh()->shipping_address_id)->toBe($alternate->id);
});

it('does a full page redirect after adding the buyer\'s first address, when that turns the Stripe card form on', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, , $request] = checkoutEligibleRequest();
    // Deliberately no BuyerAddress rows -- this add is the buyer's first,
    // so it flips cardStepReady from false to true on this response.
    $country = Country::factory()->create();

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSet('selectedAddressId', null)
        ->call('toggleNewAddressForm')
        ->set('recipient_name', 'Inline Recipient')
        ->set('phone', '080-0000-0000')
        ->set('postal_code', '100-0001')
        ->set('country_id', (string) $country->id)
        ->set('city', 'Chiyoda')
        ->set('address_line1', '1-1 Marunouchi')
        ->call('addAddress')
        ->assertHasNoErrors()
        // Not an in-place update: a real re-render here would either add
        // x-data to the <form> after the fact (Alpine won't reliably pick
        // that up on an existing element) or, once the card is already
        // mounted, wipe it via morph -- see addAddress()'s own docblock.
        ->assertRedirect(route('buyer.requests.checkout', $request->id));

    $address = BuyerAddress::where('recipient_name', 'Inline Recipient')->sole();
    expect($address->buyer_id)->toBe(BuyerProfile::where('user_id', $owner->id)->value('id'));
});

it('does not redirect after adding an address while the stub gateway is active', function () {
    [$owner, , $request] = checkoutEligibleRequest();
    $country = Country::factory()->create();

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('toggleNewAddressForm')
        ->set('recipient_name', 'Stub Recipient')
        ->set('phone', '080-0000-0000')
        ->set('postal_code', '100-0001')
        ->set('country_id', (string) $country->id)
        ->set('city', 'Chiyoda')
        ->set('address_line1', '1-1 Marunouchi')
        ->call('addAddress')
        ->assertHasNoErrors()
        ->assertNoRedirect();
});

it('does not load Stripe.js or show the card form when the stub gateway is active', function () {
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertDontSee(__('buyer.checkout.card_section'))
        ->assertDontSee('js.stripe.com');
});

it('does not show the card form for a free (無償) request even when the stripe gateway is active', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = freeCheckoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertDontSee(__('buyer.checkout.card_section'))
        ->assertDontSee('js.stripe.com');
});

it('pay() returns the PaymentIntent client secret instead of redirecting, when the stripe gateway is active', function () {
    config(['payments.gateway' => 'stripe']);
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    ApiRequestor::setHttpClient(new FakeStripeHttpClientForCheckoutTest([
        'id' => 'pi_ui_test',
        'object' => 'payment_intent',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_ui_test_secret',
    ]));

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('pay')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertReturned(fn ($data) => $data['clientSecret'] === 'pi_ui_test_secret'
            && $data['redirectUrl'] === route('buyer.requests.show', $request->id));

    // CheckoutAction already ran (the whole point) -- but the payment
    // itself stays pending. Only the webhook is ever allowed to confirm
    // it (CLAUDE.md §14 stripe integration).
    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Paid);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway)->toBe('stripe')
        ->and($payment->gateway_reference)->toBe('pi_ui_test');
});

// --- 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5) ---------------------

/**
 * @return array{0: User, 1: BuyerProfile, 2: PartRequest}
 */
function freeCheckoutEligibleRequest(int $costPrice = 45_000): array
{
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($profile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    $vendor = VendorProfile::factory()->create(['company_name' => 'Secret Vendor Co']);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => $costPrice,
        'weight_kg' => 12,
    ]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    return [$owner, $profile, $request->fresh()];
}

it('shows the free-order confirmation screen -- no fee breakdown, address only', function () {
    [$owner, $profile, $request] = freeCheckoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertSee(__('buyer.checkout.confirm_free_button'))
        ->assertSee(__('buyer.checkout.free_order_note'))
        ->assertDontSee(__('buyer.checkout.pay_button'))
        ->assertDontSee(__('buyer.checkout.summary_section'));
});

it('confirms a free order: snapshots the address, records a ¥0 confirmed payment, and redirects', function () {
    [$owner, $profile, $request] = freeCheckoutEligibleRequest();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('confirmFree')
        ->assertHasNoErrors()
        ->assertRedirect(route('buyer.requests.show', $request->id))
        ->assertSessionHas('status', __('buyer.checkout.free_confirmed', ['code' => $request->request_code]));

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Paid)
        ->and($fresh->shipping_address_id)->toBe($address->id);

    $payment = Payment::where('part_request_id', $request->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->amount)->toBe(0)
        ->and($payment->gateway)->toBe('waived');
});

it('rejects confirming a free order with an address that belongs to a different buyer', function () {
    [$owner, , $request] = freeCheckoutEligibleRequest();
    $othersAddress = BuyerAddress::factory()->create();

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->set('selectedAddressId', $othersAddress->id)
        ->call('confirmFree')
        ->assertHasErrors(['selectedAddressId']);

    expect(Payment::count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

it('never lets pay() charge a free request -- CheckoutAction\'s own guard reports a clean error instead', function () {
    [$owner, $profile, $request] = freeCheckoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('pay')
        ->assertHasErrors(['pay']);

    expect(Payment::count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Quoted);
});

it('never lets confirmFree() run on a paid (有償) request -- ConfirmFreeOrderAction\'s own guard reports a clean error instead', function () {
    [$owner, $profile, $request] = checkoutEligibleRequest();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($owner)
        ->test(Checkout::class, ['partRequest' => $request])
        ->call('confirmFree')
        ->assertHasErrors(['pay']);

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
