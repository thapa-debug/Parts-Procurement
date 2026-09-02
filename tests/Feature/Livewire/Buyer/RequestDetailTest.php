<?php

use App\Enums\QualityRank;
use App\Enums\RequestStatus;
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

it('shows the presented quote\'s photo, quality rank, and marked-up price -- never the vendor\'s identity, cost, or comment', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();

    $vendor = VendorProfile::factory()->create([
        'company_name' => 'Secret Vendor Co',
        'contact_person' => 'Secret Contact Person',
    ]);

    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create([
        'status' => RequestStatus::Quoted,
        'buyer_price' => 54_000,
    ]);

    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
        'quality_rank' => QualityRank::A,
        'comment' => 'Secret comment naming Secret Vendor Co directly.',
    ]);
    ResponsePhoto::factory()->create(['vendor_response_id' => $response->id, 'disk' => 'public']);

    $request->update(['selected_response_id' => $response->id, 'cost_price' => 45_000]);

    Livewire::actingAs($owner)
        ->test(RequestDetail::class, ['partRequest' => $request])
        ->assertSee(__('enums.quality_rank.a'))
        ->assertSee('54,000')
        ->assertDontSee('Secret Vendor Co')
        ->assertDontSee('Secret Contact Person')
        ->assertDontSee('45,000')
        ->assertDontSee('Secret comment naming Secret Vendor Co directly.');
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
