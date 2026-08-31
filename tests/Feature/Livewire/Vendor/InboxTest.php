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
