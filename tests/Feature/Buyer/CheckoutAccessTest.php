<?php

use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $request = PartRequest::factory()->create();

    $this->get("/buyer/requests/{$request->id}/checkout")->assertRedirect('/login');
});

it('forbids an admin', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($admin)->get("/buyer/requests/{$request->id}/checkout")->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($vendor)->get("/buyer/requests/{$request->id}/checkout")->assertForbidden();
});

it('forbids a buyer checking out another buyer\'s request', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create();

    $otherBuyer = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($otherBuyer)->create();

    $this->actingAs($otherBuyer)->get("/buyer/requests/{$request->id}/checkout")->assertForbidden();
});

it('lets the owning buyer in', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create();

    $this->actingAs($owner)
        ->get("/buyer/requests/{$request->id}/checkout")
        ->assertOk()
        ->assertSee($request->request_code);
});

// --- 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5) ---------------------
// Same route, same page -- the confirmFreeOrder ability gates it instead of
// checkout, with the identical buyer-and-owner check underneath.

it('forbids a buyer confirming another buyer\'s free request', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['is_free' => true]);

    $otherBuyer = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($otherBuyer)->create();

    $this->actingAs($otherBuyer)->get("/buyer/requests/{$request->id}/checkout")->assertForbidden();
});

it('lets the owning buyer into their own free request\'s confirmation screen', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);

    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 30_000,
        'weight_kg' => 5,
    ]);
    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response, isFree: true);
    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    $this->actingAs($owner)
        ->get("/buyer/requests/{$request->id}/checkout")
        ->assertOk()
        ->assertSee($request->request_code)
        ->assertSee(__('buyer.checkout.confirm_free_button'));
});
