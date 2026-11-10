<?php

use App\Livewire\Vendor\Inbox;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not let a buyer or admin mount the vendor inbox', function () {
    $buyer = User::factory()->buyer()->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($buyer)->test(Inbox::class)->assertForbidden();
    Livewire::actingAs($admin)->test(Inbox::class)->assertForbidden();
});

it('lists only requests broadcast to this vendor, split into pending and responded', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();

    $pendingRequest = PartRequest::factory()->create(['part_name' => 'Pending bumper']);
    $pendingRequest->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    $respondedRequest = PartRequest::factory()->create(['part_name' => 'Responded headlight']);
    $respondedRequest->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);
    VendorResponse::factory()->create([
        'part_request_id' => $respondedRequest->id,
        'vendor_id' => $vendorProfile->id,
    ]);

    $notInvitedRequest = PartRequest::factory()->create(['part_name' => 'Never sent to me']);

    Livewire::actingAs($vendorUser)
        ->test(Inbox::class)
        ->assertSee('Pending bumper')
        ->assertSee('Responded headlight')
        ->assertDontSee('Never sent to me');
});

it('never shows another vendor\'s invited requests or the buyer\'s identity', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();

    $otherVendorUser = User::factory()->vendor()->create();
    $otherVendorProfile = VendorProfile::factory()->for($otherVendorUser)->create();

    $buyerUser = User::factory()->buyer()->create();
    $buyerProfile = BuyerProfile::factory()->for($buyerUser)->create(['company_name' => 'Secret Buyer Co']);

    $otherVendorsRequest = PartRequest::factory()->for($buyerProfile, 'buyer')->create(['part_name' => 'Not mine']);
    $otherVendorsRequest->vendors()->attach($otherVendorProfile->id, ['invited_at' => now()]);

    $myRequest = PartRequest::factory()->for($buyerProfile, 'buyer')->create(['part_name' => 'Mine']);
    $myRequest->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    Livewire::actingAs($vendorUser)
        ->test(Inbox::class)
        ->assertSee('Mine')
        ->assertDontSee('Not mine')
        ->assertDontSee('Secret Buyer Co');
});

// --- confirmed orders stand out ------------------------------------------------------

it('lists a confirmed order in its own prominent section, ahead of and not under responded', function () {
    [$request, $confirmedUser] = confirmedOrderWithTwoVendors();

    Livewire::actingAs($confirmedUser)
        ->test(Inbox::class)
        ->assertSeeInOrder([
            __('vendor.inbox.confirmed_section'),
            __('vendor.inbox.confirmed_badge'),
            $request->request_code,
            __('vendor.inbox.pending_section'),
            __('vendor.inbox.responded_section'),
            __('vendor.inbox.responded_empty'),
        ]);
});

it('shows another invited vendor the same request as plainly responded, with no confirmed badge', function () {
    [$request, , $otherUser] = confirmedOrderWithTwoVendors();

    Livewire::actingAs($otherUser)
        ->test(Inbox::class)
        ->assertDontSee(__('vendor.inbox.confirmed_section'))
        ->assertDontSee(__('vendor.inbox.confirmed_badge'))
        ->assertSeeInOrder([__('vendor.inbox.responded_section'), $request->request_code]);
});

it('has no confirmed section at all while nothing is confirmed', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    Livewire::actingAs($vendorUser)
        ->test(Inbox::class)
        ->assertDontSee(__('vendor.inbox.confirmed_section'))
        ->assertSee($request->request_code);
});

it('never shows the buyer\'s identity or shipping address alongside a confirmed order', function () {
    [, $confirmedUser] = confirmedOrderWithTwoVendors();

    $html = Livewire::actingAs($confirmedUser)->test(Inbox::class)->html();

    expect($html)->not->toContain('Secret Buyer Co')
        ->and($html)->not->toContain('Buyer Recipient Person')
        ->and($html)->not->toContain('42 Buyer Street');
});
