<?php

use App\Actions\PresentQuoteAction;
use App\Enums\LeadTime;
use App\Enums\QualityRank;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Livewire\Admin\RequestDetail;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\PresentedQuote;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization -----------------------------------------------------

it('does not let a buyer or vendor mount the request detail component', function () {
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();
    $request = PartRequest::factory()->create();

    Livewire::actingAs($buyer)->test(RequestDetail::class, ['partRequest' => $request])->assertForbidden();
    Livewire::actingAs($vendor)->test(RequestDetail::class, ['partRequest' => $request])->assertForbidden();
});

it('lets an admin mount the request detail component', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee($request->request_code)
        ->assertSee($request->part_name);
});

// --- vendor selection ----------------------------------------------------

it('offers only active vendors as selectable, excluding suspended ones', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();
    $active = VendorProfile::factory()->create(['company_name' => 'Active Co']);
    $suspended = VendorProfile::factory()->suspended()->create(['company_name' => 'Suspended Co']);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee('Active Co')
        ->assertDontSee('Suspended Co');
});

it('defaults every active vendor as selected', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    VendorProfile::factory()->suspended()->create();

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSet('selectedVendorIds', fn ($ids) => in_array($vendorA->id, $ids) && in_array($vendorB->id, $ids) && count($ids) === 2);
});

it('re-selects every active vendor via the select-all action', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->set('selectedVendorIds', [$vendorA->id])
        ->call('selectAllVendors')
        ->assertSet('selectedVendorIds', fn ($ids) => count($ids) === 2 && in_array($vendorB->id, $ids));
});

it('shows a helpful message instead of an empty checkbox list when there are no active vendors', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();
    VendorProfile::factory()->suspended()->create();

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.no_active_vendors'));
});

// --- sending the inquiry -------------------------------------------------

it('sends the inquiry, transitions the request, and shows a confirmation', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->call('sendInquiry')
        ->assertSet('sentToCount', 2)
        ->assertSee(__('admin.request_detail.sent_confirmation', ['count' => 2]))
        ->assertDispatched('toast', message: __('admin.request_detail.sent_confirmation', ['count' => 2]), type: 'success');

    expect($request->fresh()->status)->toBe(RequestStatus::VendorInquiry)
        ->and($request->fresh()->vendors()->pluck('vendor_profiles.id')->sort()->values()->all())
        ->toBe(collect([$vendorA->id, $vendorB->id])->sort()->values()->all());
});

it('shows the invited-vendors list instead of the selection form once already broadcast', function () {
    $admin = User::factory()->admin()->create();
    $vendor = VendorProfile::factory()->create(['company_name' => 'Yamato Auto']);
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee('Yamato Auto')
        ->assertDontSee(__('admin.request_detail.send_button'));
});

it('rejects sending with no vendors selected', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);
    VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->set('selectedVendorIds', [])
        ->call('sendInquiry')
        ->assertHasErrors(['selectedVendorIds'])
        ->assertSet('sentToCount', null)
        ->assertDispatched('toast', message: __('admin.request_detail.select_at_least_one'), type: 'error');

    expect($request->fresh()->status)->toBe(RequestStatus::New);
});

// --- comparing and presenting quotes ---------------------------------------

it('shows a waiting message in the compare section until at least one vendor has responded', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.compare_section'))
        ->assertSee(__('admin.request_detail.no_vendor_responses'));
});

it('never shows the compare section before the request has been broadcast', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('admin.request_detail.compare_section'));
});

it('shows each vendor response\'s cost, computed buyer price, quality rank, lead time, and comment', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create(['company_name' => 'Yamato Auto']);
    VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'quality_rank' => QualityRank::A,
        'lead_time' => LeadTime::Within1Week,
        'comment' => 'Clean, no visible damage.',
    ]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee('Yamato Auto')
        ->assertSee('45,000')
        // max(45000 * 20%, 2000) = 9000 -> buyer_price 54000
        ->assertSee('54,000')
        ->assertSee(__('enums.quality_rank.a'))
        ->assertSee(__('enums.lead_time.within_1_week'))
        ->assertSee('Clean, no visible damage.')
        ->assertSee(__('admin.request_detail.present_checkbox_label'));
});

it('shows a no-stock badge instead of price fields, with no present checkbox, for a no-stock reply', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    VendorResponse::factory()->noStock()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
    ]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('admin.request_detail.no_stock_badge'))
        ->assertDontSee(__('admin.request_detail.present_checkbox_label'));
});

it('presents every checked quote in one deliberate batch action, each snapshotting its own price', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->set('selectedResponseIdsToPresent', [$responseA->id, $responseB->id])
        ->call('presentSelectedQuotes')
        ->assertHasNoErrors()
        ->assertSet('selectedResponseIdsToPresent', [])
        ->assertSee(__('admin.request_detail.presented_badge'))
        ->assertDispatched('toast', message: __('admin.request_detail.presented_toast', ['count' => 2]), type: 'success');

    $fresh = $request->fresh();
    // Presenting alone never selects anything (client revision --
    // that's SelectQuoteAction's own, separate job).
    expect($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->selected_response_id)->toBeNull()
        ->and(PresentedQuote::count())->toBe(2)
        ->and(PresentedQuote::where('vendor_response_id', $responseA->id)->value('buyer_price'))->toBe(36_000)
        ->and(PresentedQuote::where('vendor_response_id', $responseB->id)->value('buyer_price'))->toBe(60_000);
});

// --- 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5) ---------------------

it('presents a batch as free (無償) when checked, forcing buyer_price and shipping_fee to zero', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->set('selectedResponseIdsToPresent', [$responseA->id, $responseB->id])
        ->set('presentAsFree', true)
        ->call('presentSelectedQuotes')
        ->assertHasNoErrors()
        ->assertSee(__('admin.request_detail.free_badge'));

    expect(PresentedQuote::count())->toBe(2)
        ->and(PresentedQuote::where('vendor_response_id', $responseA->id)->value('buyer_price'))->toBe(0)
        ->and(PresentedQuote::where('vendor_response_id', $responseA->id)->value('shipping_fee'))->toBe(0)
        ->and(PresentedQuote::where('vendor_response_id', $responseA->id)->value('is_free'))->toBeTrue()
        ->and(PresentedQuote::where('vendor_response_id', $responseB->id)->value('is_free'))->toBeTrue();
});

it('shows the free quote\'s frozen ¥0 buyer price and shipping fee once presented, not the live preview', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);

    app(PresentQuoteAction::class)->execute($request, $response, isFree: true);

    // The live preview would show the real computed buyer_price/shipping
    // fee (non-zero) -- this proves the actually-presented ¥0 figures win
    // once a response has been presented, not that stale preview.
    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertDontSee('54,000')
        ->assertSeeInOrder([
            __('admin.request_detail.buyer_price_column'),
            '¥0',
        ]);
});

it('keeps showing a presented quote\'s frozen buyer price after the margin rate changes, not a recomputed preview', function () {
    Setting::set('margin_rate', 20, 'integer');
    Setting::set('margin_min_fee', 2000, 'integer');

    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    // max(45000 * 20%, 2000) = 9000 -> 54,000
    app(PresentQuoteAction::class)->execute($request, $response);

    Setting::set('margin_rate', 50, 'integer');

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertSee('54,000')
        // max(45000 * 50%, 2000) = 22,500 -> 67,500, the live preview's
        // figure -- must never appear once the quote is actually presented.
        ->assertDontSee('67,500');
});

it('ignores any typed shipping-fee override when presenting as free', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->set('selectedResponseIdsToPresent', [$response->id])
        // Stale leftover values from before the admin checked "free" --
        // must never reach PresentQuoteAction as a real override.
        ->set("shippingFeeOverrides.{$response->id}", '50000')
        ->set('presentAsFree', true)
        ->call('presentSelectedQuotes')
        ->assertHasNoErrors();

    expect(PresentedQuote::where('vendor_response_id', $response->id)->value('shipping_fee'))->toBe(0)
        ->and(PresentedQuote::where('vendor_response_id', $response->id)->value('shipping_fee_overridden'))->toBeFalse();
});

it('rejects presenting when nothing is checked', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->call('presentSelectedQuotes')
        ->assertHasErrors(['presentQuote'])
        ->assertDispatched('toast', message: __('admin.request_detail.select_at_least_one_quote'), type: 'error');

    expect(PresentedQuote::count())->toBe(0);
});

it('distinguishes the buyer-selected quote from merely-presented ones', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $responseA = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 30_000]);
    $responseB = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 50_000]);

    app(PresentQuoteAction::class)->execute($request, $responseA);
    app(PresentQuoteAction::class)->execute($request->fresh(), $responseB);

    // Simulates the buyer having picked quote A (SelectQuoteAction's own
    // job -- not exercised here, just its effect on the badge).
    $request->fresh()->update(['selected_response_id' => $responseA->id, 'buyer_price' => 39_000]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        // Both badges render at once here: quote A is presented AND the
        // buyer's pick; quote B is presented but not selected -- so
        // "Presented" must appear (for B, and also for A), while "Buyer
        // selected" appears exactly once (only for A).
        ->assertSeeInOrder([
            __('admin.request_detail.presented_badge'),
            __('admin.request_detail.buyer_selected_badge'),
        ])
        // Neither presented response shows a checkbox any more -- there is
        // no remove action of any kind, just the badges.
        ->assertDontSee(__('admin.request_detail.present_checkbox_label'));
});

it('locks out presenting entirely and shows the locked help text once the request has been paid for', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $presentedResponse = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorA->id, 'cost_price' => 45_000]);
    $unpresentedResponse = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendorB->id, 'cost_price' => 30_000]);

    app(PresentQuoteAction::class)->execute($request, $presentedResponse);
    $request->fresh()->update(['status' => RequestStatus::Paid]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertSee(__('admin.request_detail.compare_locked_help'))
        ->assertSee(__('admin.request_detail.presented_badge'))
        // The never-presented response still shows its checkbox, but
        // disabled -- and the batch "Present to buyer" button is gone.
        ->assertSeeHtml('disabled')
        ->assertDontSee(__('admin.request_detail.present_selected_button'));
});

it('shows the payment summary -- amount, gateway, shipping method and address -- once paid', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create([
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
    Payment::factory()->confirmed()->create(['part_request_id' => $request->id, 'amount' => 62_000, 'gateway' => 'stub']);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request->fresh()])
        ->assertSee(__('admin.request_detail.paid_banner_heading'))
        ->assertSee('62,000')
        ->assertSee('stub')
        ->assertSee(__('enums.shipping_method.standard'))
        ->assertSee('Jane Doe')
        ->assertSee('123 Main St');
});

it('does not show a payment summary before the request has been paid', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertDontSee(__('admin.request_detail.payment_summary_section'));
});
