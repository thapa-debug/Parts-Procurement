<?php

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $profile = VendorProfile::factory()->create();

    $this->get("/admin/vendors/{$profile->id}")->assertRedirect('/login');
});

it('forbids a buyer', function () {
    $buyer = User::factory()->buyer()->create();
    $profile = VendorProfile::factory()->create();

    $this->actingAs($buyer)->get("/admin/vendors/{$profile->id}")->assertForbidden();
});

it('forbids a vendor, including viewing their own profile', function () {
    $vendor = User::factory()->vendor()->create();
    $profile = VendorProfile::factory()->for($vendor)->create();

    $this->actingAs($vendor)->get("/admin/vendors/{$profile->id}")->assertForbidden();
});

it('lets an admin in and shows the company name', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create(['company_name' => 'Acme Dismantlers']);

    $this->actingAs($admin)
        ->get("/admin/vendors/{$profile->id}")
        ->assertOk()
        ->assertSee('Acme Dismantlers');
});

it('is reachable by clicking the company name from the vendor list', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create(['company_name' => 'Acme Dismantlers']);

    $this->actingAs($admin)
        ->get('/admin/vendors')
        ->assertSee(route('admin.vendors.show', $profile), false);
});
