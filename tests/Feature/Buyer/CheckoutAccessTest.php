<?php

use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
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
