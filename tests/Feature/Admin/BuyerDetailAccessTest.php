<?php

use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $profile = BuyerProfile::factory()->create();

    $this->get("/admin/buyers/{$profile->id}")->assertRedirect('/login');
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();
    $profile = BuyerProfile::factory()->create();

    $this->actingAs($vendor)->get("/admin/buyers/{$profile->id}")->assertForbidden();
});

it('forbids a buyer, including viewing their own profile', function () {
    $buyer = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($buyer)->create();

    $this->actingAs($buyer)->get("/admin/buyers/{$profile->id}")->assertForbidden();
});

it('lets an admin in and shows the company name', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);

    $this->actingAs($admin)
        ->get("/admin/buyers/{$profile->id}")
        ->assertOk()
        ->assertSee('Acme Imports');
});

it('is reachable by clicking the company name from the buyer list', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);

    $this->actingAs($admin)
        ->get('/admin/buyers')
        ->assertSee(route('admin.buyers.show', $profile), false);
});
