<?php

use App\Enums\LeadTime;
use App\Enums\QualityRank;
use App\Enums\RequestStatus;
use App\Livewire\Admin\RequestDetail;
use App\Models\PartRequest;
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
        ->assertSee(__('admin.request_detail.sent_confirmation', ['count' => 2]));

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
        ->assertSet('sentToCount', null);

    expect($request->fresh()->status)->toBe(RequestStatus::New);
});

// --- comparing and presenting quotes ---------------------------------------

it('does not show the compare section until at least one vendor has responded', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);

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
        ->assertSee(__('admin.request_detail.present_quote_button'));
});

it('shows a no-stock badge instead of price fields, with no present button, for a no-stock reply', function () {
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
        ->assertDontSee(__('admin.request_detail.present_quote_button'));
});

it('presents a quote, snapshotting the price and showing a confirmation', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->call('presentQuote', $response->id)
        ->assertSet('justPresentedBuyerPrice', 54_000)
        ->assertSee(__('admin.request_detail.present_quote_confirmation', ['price' => '54,000']));

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(RequestStatus::Quoted)
        ->and($fresh->selected_response_id)->toBe($response->id)
        ->and($fresh->buyer_price)->toBe(54_000);
});

it('hides the present button and shows the locked help text once a quote has already been presented', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    Livewire::actingAs($admin)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->call('presentQuote', $response->id)
        ->assertSee(__('admin.request_detail.compare_locked_help'))
        ->assertSee(__('admin.request_detail.presented_badge'))
        ->assertDontSee(__('admin.request_detail.present_quote_button'));
});
