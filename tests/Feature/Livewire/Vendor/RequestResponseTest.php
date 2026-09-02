<?php

use App\Enums\LeadTime;
use App\Enums\QualityRank;
use App\Livewire\Vendor\RequestResponse;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization -----------------------------------------------------

it('does not let a buyer, admin, or uninvited vendor mount the response form', function () {
    $buyer = User::factory()->buyer()->create();
    $admin = User::factory()->admin()->create();
    $uninvitedVendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($uninvitedVendorUser)->create();

    $request = PartRequest::factory()->create();

    Livewire::actingAs($buyer)->test(RequestResponse::class, ['partRequest' => $request])->assertForbidden();
    Livewire::actingAs($admin)->test(RequestResponse::class, ['partRequest' => $request])->assertForbidden();
    Livewire::actingAs($uninvitedVendorUser)->test(RequestResponse::class, ['partRequest' => $request])->assertForbidden();
});

it('lets the invited vendor mount and see the request details', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create(['part_name' => 'Front bumper assembly']);
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    Livewire::actingAs($vendorUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->assertSee($request->request_code)
        ->assertSee('Front bumper assembly');
});

// --- act gate -------------------------------------------------------------

it('blocks an unverified vendor from seeing the response form', function () {
    $vendorUser = User::factory()->vendor()->unverified()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    Livewire::actingAs($vendorUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->assertSee(__('vendor.request_response.blocked.unverified_heading'))
        ->assertDontSee(__('vendor.request_response.submit_button'));
});

// --- submitting a quote -----------------------------------------------------

it('submits a quote with multiple photos, added one at a time, and shows a confirmation', function () {
    // Pin the disk regardless of the developer's own local FILESYSTEM_DISK
    // override (CONVENTIONS.md "Local dev without S3").
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    // Each ->set() call below passes a single UploadedFile (never an array)
    // -- Livewire's test harness maps that to $isMultiple = false, exactly
    // mirroring how a real, non-`multiple` file input behaves in the
    // browser. That's what keeps this S3-safe: if the accumulation pattern
    // ever regressed to reporting more than one file per selection, this
    // would throw Livewire's own S3DoesntSupportMultipleFileUploads here,
    // since the disk is pinned to 's3' above.
    Livewire::actingAs($vendorUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->set('cost_price', '45000')
        ->set('quality_rank', QualityRank::A->value)
        ->set('lead_time', LeadTime::Within1Week->value)
        ->set('comment', 'Clean, no damage.')
        ->set('photos', UploadedFile::fake()->image('bumper-front.jpg'))
        ->set('photos', UploadedFile::fake()->image('bumper-side.jpg'))
        ->assertSet('photos', fn ($photos) => count($photos) === 2)
        ->call('sendResponse')
        ->assertSet('submitted', true)
        ->assertHasNoErrors();

    $response = VendorResponse::sole();

    expect($response->part_request_id)->toBe($request->id)
        ->and($response->vendor_id)->toBe($vendorProfile->id)
        ->and($response->cost_price)->toBe(45000)
        ->and($response->photos)->toHaveCount(2);
});

it('caps photos at MAX_PHOTOS and lets the vendor remove one before submitting', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    $component = Livewire::actingAs($vendorUser)->test(RequestResponse::class, ['partRequest' => $request]);

    foreach (range(1, RequestResponse::MAX_PHOTOS + 1) as $i) {
        $component->set('photos', UploadedFile::fake()->image("photo-{$i}.jpg"));
    }

    $component
        ->assertSet('photos', fn ($photos) => count($photos) === RequestResponse::MAX_PHOTOS)
        ->assertHasErrors('photos');

    $component->call('removePhoto', 0)
        ->assertSet('photos', fn ($photos) => count($photos) === RequestResponse::MAX_PHOTOS - 1)
        ->assertHasNoErrors('photos');
});

it('rejects a quote missing required fields', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    Livewire::actingAs($vendorUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->call('sendResponse')
        ->assertHasErrors(['cost_price', 'quality_rank', 'lead_time', 'comment', 'photos']);

    expect(VendorResponse::count())->toBe(0);
});

// --- one-tap no stock ----------------------------------------------------

it('submits a one-tap no-stock reply and shows a confirmation', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    Livewire::actingAs($vendorUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->call('sendNoStock')
        ->assertSet('submitted', true)
        ->assertSee(__('vendor.request_response.submitted_no_stock'));

    $response = VendorResponse::sole();

    expect($response->is_no_stock)->toBeTrue()
        ->and($response->cost_price)->toBeNull();
});

// --- already responded -----------------------------------------------------

it('shows the submitted summary instead of the form once already responded', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);
    VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendorProfile->id,
        'cost_price' => 30000,
        'quality_rank' => QualityRank::S,
        'lead_time' => LeadTime::Within2Days,
    ]);

    Livewire::actingAs($vendorUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->assertDontSee(__('vendor.request_response.submit_button'))
        ->assertSee('30,000');
});

// --- isolation -----------------------------------------------------------

it('never shows the buyer\'s identity or another vendor\'s response', function () {
    $buyerUser = User::factory()->buyer()->create();
    $buyerProfile = BuyerProfile::factory()->for($buyerUser)->create(['company_name' => 'Secret Buyer Co']);
    $request = PartRequest::factory()->for($buyerProfile, 'buyer')->create();

    $vendorAUser = User::factory()->vendor()->create();
    $vendorAProfile = VendorProfile::factory()->for($vendorAUser)->create();
    $vendorBUser = User::factory()->vendor()->create();
    $vendorBProfile = VendorProfile::factory()->for($vendorBUser)->create();

    $request->vendors()->attach([$vendorAProfile->id, $vendorBProfile->id], ['invited_at' => now()]);

    VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendorAProfile->id,
        'cost_price' => 99999,
        'comment' => 'Vendor A secret comment',
    ]);

    Livewire::actingAs($vendorBUser)
        ->test(RequestResponse::class, ['partRequest' => $request])
        ->assertDontSee('Secret Buyer Co')
        ->assertDontSee('99,999')
        ->assertDontSee('Vendor A secret comment')
        ->assertSee(__('vendor.request_response.submit_button'));
});
