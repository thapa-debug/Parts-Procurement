<?php

use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\QualityRank;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Livewire\Buyer\RequestDetail;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\ResponsePhoto;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization -----------------------------------------------------

it('does not let an admin, a vendor, or a different buyer mount another buyer\'s request', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();
    $otherBuyer = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($otherBuyer)->create();
    $request = PartRequest::factory()->create();

    Livewire::actingAs($admin)->test(RequestDetail::class, ['partRequest' => $request])->assertForbidden();
    Livewire::actingAs($vendor)->test(RequestDetail::class, ['partRequest' => $request])->assertForbidden();
    Livewire::actingAs($otherBuyer)->test(RequestDetail::class, ['partRequest' => $request])->assertForbidden();
});

it('lets the owning buyer mount and see their own request details', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['part_name' => 'Front bumper assembly']);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee($request->request_code)
        ->assertSee('Front bumper assembly');
});

// --- before a quote is presented ---------------------------------------

it('shows an awaiting-quote message before any quote has been presented', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('buyer.request_detail.awaiting_quote'));
});

// --- after a quote is presented: content + isolation ------------------------

it('shows a presented quote\'s photo, quality rank, and marked-up price -- never the vendor\'s identity, cost, or comment', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();

    $vendor = VendorProfile::factory()->create([
        'company_name' => 'Secret Vendor Co',
        'contact_person' => 'Secret Contact Person',
    ]);

    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'quality_rank' => QualityRank::A,
        'comment' => 'Secret comment naming Secret Vendor Co directly.',
    ]);
    ResponsePhoto::factory()->create(['vendor_response_id' => $response->id, 'disk' => 'public']);

    app(PresentQuoteAction::class)->execute($request, $response);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertSee(__('enums.quality_rank.a'))
        ->assertSee('54,000')
        ->assertSee(__('buyer.request_detail.quote_price_excludes_shipping'))
        ->assertSee(__('buyer.request_detail.select_quote_button'))
        ->assertDontSee('Secret Vendor Co')
        ->assertDontSee('Secret Contact Person')
        ->assertDontSee('45,000')
        ->assertDontSee('Secret comment naming Secret Vendor Co directly.');
});

it('shows the "Selected" badge instead of a select button for the buyer\'s current pick', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->call('selectQuote', $presentedQuote->id)
        ->assertSee(__('buyer.request_detail.quote_selected_badge'))
        ->assertDontSee(__('buyer.request_detail.select_quote_button'));

    expect($request->fresh()->selected_response_id)->toBe($response->id);
});

it('lets the buyer re-select a different presented quote at any time before paying', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    $presentedQuoteA = app(PresentQuoteAction::class)->execute($request, $responseA);
    $presentedQuoteB = app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    $component = Livewire::actingAs($owner)->test(RequestDetail::class, ['partRequest' => $request->fresh()]);

    $component->call('selectQuote', $presentedQuoteA->id);
    expect($request->fresh()->selected_response_id)->toBe($responseA->id);

    $component->call('selectQuote', $presentedQuoteB->id)->assertHasNoErrors();
    expect($request->fresh()->selected_response_id)->toBe($responseB->id);
});

it('shows every currently presented quote side by side', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    app(PresentQuoteAction::class)->execute($request, $responseA);
    app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    // max(30000*20%,2000)=6000 -> 36000; max(50000*20%,2000)=10000 -> 60000
    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertSee('36,000')
        ->assertSee('60,000');
});

// --- once paid: the selection is locked -----------------------------------

it('shows locked copy and hides the select button for other options once the request has been paid', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    $presentedA = app(PresentQuoteAction::class)->execute($request, $responseA);
    app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedA);
    $request->fresh()->update(['status' => RequestStatus::Paid]);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertSee(__('buyer.request_detail.quote_locked_help'))
        ->assertDontSee(__('buyer.request_detail.quote_options_help'))
        ->assertDontSee(__('buyer.request_detail.select_quote_button'))
        ->assertSee(__('buyer.request_detail.quote_selected_badge'));
});

it('shows a prominent payment-confirmed banner and a payment summary once paid', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create([
        'status' => RequestStatus::Paid,
        'buyer_price' => 54_000,
        'shipping_method' => ShippingMethod::Standard,
        'shipping_fee' => 8_000,
        'shipping_recipient_name' => 'Jane Doe',
        'shipping_phone' => '555-0100',
        'shipping_postal_code' => '90001',
        'shipping_country' => 'United States',
        'shipping_city' => 'Los Angeles',
        'shipping_address_line1' => '123 Main St',
    ]);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('buyer.request_detail.paid_banner_heading'))
        ->assertSee(__('buyer.request_detail.paid_banner_body'))
        ->assertSee('54,000')
        ->assertSee('8,000')
        ->assertSee('62,000') // total paid
        ->assertSee(__('enums.shipping_method.standard'))
        ->assertSee('Jane Doe')
        ->assertSee('123 Main St');
});

it('does not show the payment banner or summary before the request has been paid', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::Quoted]);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('buyer.request_detail.paid_banner_heading'))
        ->assertDontSee(__('buyer.request_detail.payment_summary_section'));
});

it('never sends the PartRequest\'s cost fields to the browser, even for a Livewire component with a "PartRequest $partRequest" method parameter name', function () {
    // Guards the specific discipline CONVENTIONS.md calls for on this page:
    // only ->partRequestId is kept as component state, so cost_price/
    // applied_rate/applied_min_fee/selected_response_id never enter
    // Livewire's client-side snapshot regardless of what the Blade view
    // renders. Inspect the actual wire snapshot payload, not just the
    // rendered HTML, to prove this at the transport layer.
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create([
        'status' => RequestStatus::Quoted,
        'cost_price' => 45_000,
        'buyer_price' => 54_000,
    ]);

    $component = Livewire::actingAs($owner)->test(RequestDetail::class, ['partRequest' => $request]);

    $snapshotJson = json_encode($component->snapshot);

    expect($snapshotJson)->not->toContain('cost_price')
        ->and($snapshotJson)->not->toContain('applied_rate')
        ->and($snapshotJson)->not->toContain('applied_min_fee')
        ->and($snapshotJson)->not->toContain('selected_response_id')
        ->and($snapshotJson)->not->toContain('45000')
        // partRequestId is the only PartRequest-shaped state that should
        // survive into the snapshot.
        ->and($snapshotJson)->toContain('partRequestId');
});

// --- multiple presented quotes: isolation must hold at N, not just 1 --------

it('never sends any vendor\'s cost, identity, or comment to the browser, even with multiple quotes presented at once', function () {
    // Client revision: multiple quotes may be presented at once
    // (presented_quotes, one row per option). The buyer-facing selection
    // UI itself is a later slice -- this proves the underlying data this
    // page's render() now builds (RequestDetail::presentedQuoteOptions())
    // can't leak vendor cost/identity at N=2, the same way the single-
    // quote case above proves it at N=1, before any Blade view exists to
    // display it.
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    $vendorA = VendorProfile::factory()->create(['company_name' => 'Secret Vendor A', 'contact_person' => 'Secret Contact A']);
    $responseA = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendorA->id,
        'cost_price' => 40_000,
        'comment' => 'Secret comment naming Vendor A directly.',
    ]);

    $vendorB = VendorProfile::factory()->create(['company_name' => 'Secret Vendor B', 'contact_person' => 'Secret Contact B']);
    $responseB = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendorB->id,
        'cost_price' => 60_000,
        'comment' => 'Secret comment naming Vendor B directly.',
    ]);

    app(PresentQuoteAction::class)->execute($request, $responseA);
    app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    $component = Livewire::actingAs($owner)->test(RequestDetail::class, ['partRequest' => $request->fresh()]);

    $snapshotJson = json_encode($component->snapshot);

    expect($snapshotJson)->not->toContain('Secret Vendor A')
        ->and($snapshotJson)->not->toContain('Secret Vendor B')
        ->and($snapshotJson)->not->toContain('Secret Contact A')
        ->and($snapshotJson)->not->toContain('Secret Contact B')
        ->and($snapshotJson)->not->toContain('naming Vendor A')
        ->and($snapshotJson)->not->toContain('naming Vendor B')
        ->and($snapshotJson)->not->toContain('40000')
        ->and($snapshotJson)->not->toContain('60000')
        ->and($snapshotJson)->not->toContain('vendor_response_id')
        ->and($snapshotJson)->not->toContain('cost_price')
        ->and($snapshotJson)->toContain('partRequestId');

    $component
        ->assertDontSee('Secret Vendor A')
        ->assertDontSee('Secret Vendor B')
        ->assertDontSee('Secret Contact A')
        ->assertDontSee('Secret Contact B')
        ->assertDontSee('naming Vendor A')
        ->assertDontSee('naming Vendor B')
        ->assertDontSee('40,000')
        ->assertDontSee('60,000');
});
