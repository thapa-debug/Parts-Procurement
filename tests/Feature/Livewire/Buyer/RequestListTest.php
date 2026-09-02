<?php

use App\Livewire\Buyer\RequestList;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not let an admin or vendor mount the request list', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($admin)->test(RequestList::class)->assertForbidden();
    Livewire::actingAs($vendor)->test(RequestList::class)->assertForbidden();
});

it('lists only the current buyer\'s own requests, never another buyer\'s', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    PartRequest::factory()->for($ownerProfile, 'buyer')->create(['part_name' => 'Mine']);

    $otherBuyer = User::factory()->buyer()->create();
    $otherProfile = BuyerProfile::factory()->for($otherBuyer)->create();
    PartRequest::factory()->for($otherProfile, 'buyer')->create(['part_name' => 'Not mine']);

    Livewire::actingAs($owner)
        ->test(RequestList::class)
        ->assertSee('Mine')
        ->assertDontSee('Not mine');
});

it('shows an empty-state message when the buyer has no requests yet', function () {
    $buyer = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($buyer)->create();

    Livewire::actingAs($buyer)
        ->test(RequestList::class)
        ->assertSee(__('buyer.request_list.empty'));
});
