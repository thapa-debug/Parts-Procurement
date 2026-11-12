<?php

use App\Actions\CheckoutAction;
use App\Actions\SetUrlPriceAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Livewire\Admin\RequestBoard;
use App\Livewire\Admin\RequestDetail as AdminRequestDetail;
use App\Livewire\Buyer\Checkout;
use App\Livewire\Buyer\RequestDetail as BuyerRequestDetail;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ShippingWeightBracket;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\UrlQuotePricedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// The reference-URL (admin-sourced) flow's screens: the admin's pricing
// form and mark-purchased panel, and the buyer's quote card + checkout.
// The actions themselves are covered by SetUrlPriceActionTest,
// MarkPurchasedFromUrlActionTest and UrlSourcedFlowTest.

beforeEach(function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 2]);
});

// The mark-purchased button carrying a real `disabled` attribute.
const MARK_PURCHASED_BUTTON_DISABLED = '/<button[^>]*wire:click="markPurchasedFromUrl"[^>]*\\sdisabled(?=[\\s>=])/s';

/**
 * A buyer's `new` request with a reference URL.
 *
 * @return array{0: PartRequest, 1: User, 2: BuyerProfile}
 */
function newUrlRequestOnScreen(?string $url = 'https://example.com/listing/123'): array
{
    $buyer = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($buyer)->create();
    $request = PartRequest::factory()->for($profile, 'buyer')->create([
        'status' => RequestStatus::New,
        'reference_url' => $url,
    ]);

    return [$request, $buyer, $profile];
}

/**
 * Priced from its URL: cost 45,000 → buyer price 54,000; 12 kg → 8,000.
 *
 * @return array{0: PartRequest, 1: User, 2: BuyerProfile}
 */
function urlPricedRequestOnScreen(): array
{
    [$request, $buyer, $profile] = newUrlRequestOnScreen();

    Notification::fake();
    $request = app(SetUrlPriceAction::class)->execute($request, urlCost: 45_000, weightKg: 12);

    return [$request, $buyer, $profile];
}

/** A URL-priced request at `paid`, with a payment in the given status. */
function paidUrlRequestOnScreen(PaymentStatus $paymentStatus = PaymentStatus::Confirmed): PartRequest
{
    [$request] = urlPricedRequestOnScreen();
    $request->update(['status' => RequestStatus::Paid]);
    Payment::factory()->create([
        'part_request_id' => $request->id,
        'status' => $paymentStatus,
        'paid_at' => $paymentStatus === PaymentStatus::Confirmed ? now() : null,
    ]);

    return $request->fresh();
}

// --- admin: pricing form at `new` -------------------------------------------------

it('offers URL pricing alongside the vendor broadcast for a new request with a reference URL', function () {
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.broadcast_section'))
        ->assertSee(__('admin.request_detail.url_source_section'));
});

it('does not offer URL pricing when the request has no reference URL', function () {
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen(null);

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('admin.request_detail.url_source_section'));
});

it('does not offer URL pricing once the request has been sent to vendors', function () {
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();
    $request->update(['status' => RequestStatus::VendorInquiry]);

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('admin.request_detail.url_source_section'));
});

it('previews the marked-up price and calculated shipping before pricing', function () {
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.url_preview_incomplete'))
        ->set('urlCost', '45000')
        ->set('urlWeightKg', '12')
        ->assertSee('¥54,000') // 45,000 + 20 %
        ->assertSee('¥8,000') // catch-all bracket
        ->assertSee('¥62,000'); // buyer total
});

it('prices the request from its URL, notifies the buyer, and swaps the form for the frozen snapshot', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [$request, $buyer] = newUrlRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->set('urlCost', '45000')
        ->set('urlWeightKg', '12')
        ->call('setUrlPrice')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success')
        ->assertDontSee(__('admin.request_detail.url_source_section'))
        ->assertDontSee(__('admin.request_detail.broadcast_section'))
        ->assertDontSee(__('admin.request_detail.compare_section'))
        ->assertSee(__('admin.request_detail.url_priced_section'))
        ->assertSee('45,000')
        ->assertSee('54,000');

    $request->refresh();

    expect($request->status)->toBe(RequestStatus::Quoted)
        ->and($request->is_url_sourced)->toBeTrue()
        ->and($request->buyer_price)->toBe(54_000)
        ->and($request->url_shipping_fee)->toBe(8_000);

    Notification::assertSentTo($buyer, UrlQuotePricedNotification::class);
});

it('stores a shipping override with its reason and shows both to the admin', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->set('urlCost', '45000')
        ->set('urlWeightKg', '12')
        ->set('urlShippingFeeOverride', '6500')
        ->set('urlShippingFeeOverrideReason', 'Fits a smaller box')
        ->call('setUrlPrice')
        ->assertHasNoErrors()
        ->assertSee('6,500')
        ->assertSee('Fits a smaller box');

    expect($request->fresh())
        ->url_shipping_fee->toBe(6_500)
        ->url_shipping_fee_overridden->toBeTrue();
});

it('validates the pricing form before calling the action', function (array $input, string $errorField) {
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();

    $component = Livewire::actingAs($admin)->test(AdminRequestDetail::class, ['partRequest' => $request]);

    foreach ($input as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('setUrlPrice')->assertHasErrors([$errorField]);

    expect($request->fresh()->status)->toBe(RequestStatus::New);
})->with([
    'missing cost' => [['urlWeightKg' => '12'], 'urlCost'],
    'zero cost' => [['urlCost' => '0', 'urlWeightKg' => '12'], 'urlCost'],
    'fractional cost' => [['urlCost' => '100.5', 'urlWeightKg' => '12'], 'urlCost'],
    'missing weight' => [['urlCost' => '45000'], 'urlWeightKg'],
    'zero weight' => [['urlCost' => '45000', 'urlWeightKg' => '0'], 'urlWeightKg'],
    'negative override' => [['urlCost' => '45000', 'urlWeightKg' => '12', 'urlShippingFeeOverride' => '-1', 'urlShippingFeeOverrideReason' => 'x'], 'urlShippingFeeOverride'],
    'override without reason' => [['urlCost' => '45000', 'urlWeightKg' => '12', 'urlShippingFeeOverride' => '6500'], 'urlShippingFeeOverrideReason'],
]);

it('surfaces an error, not a crash, when no shipping bracket covers the weight', function () {
    ShippingWeightBracket::query()->delete();
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->set('urlCost', '45000')
        ->set('urlWeightKg', '12')
        ->assertSee(__('admin.request_detail.url_preview_no_bracket'))
        ->call('setUrlPrice')
        ->assertHasErrors(['urlWeightKg'])
        ->assertDispatched('toast', type: 'error');

    expect($request->fresh()->status)->toBe(RequestStatus::New);
});

it('refuses a forced URL pricing call after the request was sent to vendors', function () {
    $admin = User::factory()->admin()->create();
    [$request] = newUrlRequestOnScreen();

    $component = Livewire::actingAs($admin)->test(AdminRequestDetail::class, ['partRequest' => $request]);

    // Another admin broadcasts it in the meantime.
    PartRequest::whereKey($request->id)->update(['status' => RequestStatus::VendorInquiry]);

    $component->set('urlCost', '45000')
        ->set('urlWeightKg', '12')
        ->call('setUrlPrice')
        ->assertDispatched('toast', type: 'error');

    expect($request->fresh())
        ->status->toBe(RequestStatus::VendorInquiry)
        ->is_url_sourced->toBeFalse();
});

// --- admin: mark purchased at `paid` --------------------------------------------------

it('shows the mark-purchased panel, not the confirm-to-vendor panel, for a paid URL request', function () {
    $admin = User::factory()->admin()->create();
    $request = paidUrlRequestOnScreen();
    CompanyAddress::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.url_purchase_section'))
        ->assertDontSee(__('admin.request_detail.confirm_order_section'));

    expect($component->html())->not->toMatch(MARK_PURCHASED_BUTTON_DISABLED);
});

it('marks a paid URL request purchased with its reference', function () {
    $admin = User::factory()->admin()->create();
    $request = paidUrlRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->set('urlPurchaseReference', 'Order #A-778')
        ->call('markPurchasedFromUrl')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success')
        ->assertDontSee(__('admin.request_detail.url_purchase_section'))
        ->assertSee(__('admin.request_detail.url_purchased_section'))
        ->assertSee('Order #A-778');

    expect($request->fresh())
        ->status->toBe(RequestStatus::OrderedToVendor)
        ->url_purchase_reference->toBe('Order #A-778');
});

it('disables mark-purchased while the payment is pending, and refuses a forced call -- the §6.3 gate', function () {
    $admin = User::factory()->admin()->create();
    $request = paidUrlRequestOnScreen(PaymentStatus::Pending);

    $component = Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.confirm_order_payment_pending'));

    expect($component->html())->toMatch(MARK_PURCHASED_BUTTON_DISABLED);

    $component->call('markPurchasedFromUrl')->assertDispatched('toast', type: 'error');

    expect($request->fresh()->status)->toBe(RequestStatus::Paid);
});

it('never shows the mark-purchased panel on a vendor-flow request', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::Paid]);

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('admin.request_detail.url_purchase_section'));
});

it('does not let a buyer or vendor run the URL actions', function () {
    [$request, $buyer] = newUrlRequestOnScreen();
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor)->create();

    Livewire::actingAs($buyer)->test(AdminRequestDetail::class, ['partRequest' => $request])->assertForbidden();
    Livewire::actingAs($vendor)->test(AdminRequestDetail::class, ['partRequest' => $request])->assertForbidden();
});

// --- buyer: quote card + checkout ---------------------------------------------------------

it('shows the buyer one priced quote with a checkout button -- not "awaiting quotes"', function () {
    [$request, $buyer] = urlPricedRequestOnScreen();

    Livewire::actingAs($buyer)
        ->test(BuyerRequestDetail::class, ['partRequest' => $request])
        ->assertSee('¥54,000')
        ->assertSee(__('buyer.request_detail.quote_single_help'))
        ->assertSee(__('buyer.request_detail.checkout_button'))
        ->assertDontSee(__('buyer.request_detail.awaiting_quote'))
        ->assertDontSee('45,000');
});

it('never tells the buyer the request was URL-sourced', function () {
    [$request, $buyer] = urlPricedRequestOnScreen();

    $component = Livewire::actingAs($buyer)->test(BuyerRequestDetail::class, ['partRequest' => $request]);

    $component->assertDontSee(__('admin.request_detail.url_priced_badge'));

    expect(json_encode($component->snapshot))->not->toContain('is_url_sourced');
});

it('hides the checkout button once the buyer has paid', function () {
    [$request, $buyer, $profile] = urlPricedRequestOnScreen();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id]);
    $request = app(CheckoutAction::class)->execute($request, $address);

    Livewire::actingAs($buyer)
        ->test(BuyerRequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('buyer.request_detail.quote_locked_help'))
        ->assertDontSee(__('buyer.request_detail.checkout_button'));
});

it('lets the buyer check out and pay a URL-priced request', function () {
    [$request, $buyer, $profile] = urlPricedRequestOnScreen();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    Livewire::actingAs($buyer)
        ->test(Checkout::class, ['partRequest' => $request])
        ->assertDontSee(__('buyer.checkout.not_eligible'))
        ->assertSee('54,000')
        ->assertSee('8,000')
        ->call('pay')
        ->assertHasNoErrors()
        ->assertRedirect(route('buyer.requests.show', $request->id));

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Paid)
        ->and($fresh->shipping_address_id)->toBe($address->id)
        ->and(Payment::where('part_request_id', $request->id)->sole()->amount)->toBe(62_000);
});

// --- admin: progress stepper ----------------------------------------------------------------

it('shows Vendor inquiry as skipped, not completed, on a URL-sourced request', function () {
    $admin = User::factory()->admin()->create();
    [$request] = urlPricedRequestOnScreen();

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.stepper.skipped_url_sourced'));
});

it('never shows the skipped caption on a vendor-flow request', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);

    Livewire::actingAs($admin)
        ->test(AdminRequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('admin.request_detail.stepper.skipped_url_sourced'));
});

// --- admin: request board badges ------------------------------------------------------------

it('flags a new request with a reference URL on the board, and only that one', function () {
    $admin = User::factory()->admin()->create();
    newUrlRequestOnScreen();
    PartRequest::factory()->create(['status' => RequestStatus::New, 'reference_url' => null]);
    // Already sent to vendors: it can no longer be sourced from its URL.
    PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry, 'reference_url' => 'https://example.com/other']);

    $html = Livewire::actingAs($admin)->test(RequestBoard::class)->html();

    expect(substr_count($html, __('admin.request_board.reference_url_badge_title')))->toBe(1)
        ->and($html)->not->toContain(__('admin.request_board.url_sourced_badge'));
});

it('shows URL-sourced, not the reference-URL badge, once a request has been priced from its URL', function () {
    $admin = User::factory()->admin()->create();
    urlPricedRequestOnScreen();

    $html = Livewire::actingAs($admin)->test(RequestBoard::class)->html();

    expect(substr_count($html, __('admin.request_board.url_sourced_badge')))->toBe(1)
        ->and($html)->not->toContain(__('admin.request_board.reference_url_badge_title'));
});
