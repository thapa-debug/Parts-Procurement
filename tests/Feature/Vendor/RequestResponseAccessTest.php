<?php

use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $request = PartRequest::factory()->create();

    $this->get("/vendor/inbox/{$request->id}")->assertRedirect('/login');
});

it('forbids a buyer', function () {
    $buyer = User::factory()->buyer()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($buyer)->get("/vendor/inbox/{$request->id}")->assertForbidden();
});

it('forbids an admin', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($admin)->get("/vendor/inbox/{$request->id}")->assertForbidden();
});

it('forbids a vendor who was never invited to this request', function () {
    $vendorUser = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($vendorUser)->get("/vendor/inbox/{$request->id}")->assertForbidden();
});

it('lets in a vendor actually invited to this request', function () {
    $vendorUser = User::factory()->vendor()->create();
    $vendorProfile = VendorProfile::factory()->for($vendorUser)->create();
    $request = PartRequest::factory()->create();
    $request->vendors()->attach($vendorProfile->id, ['invited_at' => now()]);

    $this->actingAs($vendorUser)
        ->get("/vendor/inbox/{$request->id}")
        ->assertOk()
        ->assertSee($request->request_code);
});
