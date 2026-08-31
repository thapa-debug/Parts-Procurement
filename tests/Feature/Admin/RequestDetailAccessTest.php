<?php

use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $request = PartRequest::factory()->create();

    $this->get("/admin/requests/{$request->id}")->assertRedirect('/login');
});

it('forbids a buyer, even the request\'s own owner', function () {
    $buyer = User::factory()->buyer()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($buyer)->get("/admin/requests/{$request->id}")->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($vendor)->get("/admin/requests/{$request->id}")->assertForbidden();
});

it('lets an admin in', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/requests/{$request->id}")
        ->assertOk()
        ->assertSee($request->request_code);
});
