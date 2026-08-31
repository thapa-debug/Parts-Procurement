<?php

use App\Enums\RequestStatus;
use App\Livewire\Admin\RequestDetail;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
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
