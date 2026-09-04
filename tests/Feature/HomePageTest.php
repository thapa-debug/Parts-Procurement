<?php

use App\Actions\CreateAdminManagedUserAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('boots the application and serves the home page', function () {
    $this->get('/')->assertOk();
});

// --- vendor home (client revision: vendor inbox is the vendor's landing page) --

it('redirects a logged-in vendor from the home page straight to their inbox', function () {
    $vendor = User::factory()->vendor()->create();

    $this->actingAs($vendor)
        ->get('/')
        ->assertRedirect(route('vendor.inbox'));
});

it('does not redirect an admin or buyer away from the generic home page', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();

    $this->actingAs($admin)->get('/')->assertOk();
    $this->actingAs($buyer)->get('/')->assertOk();
});

it('still sends a vendor to the password-change page first, ahead of the inbox, when a password change is required', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    $this->get('/')->assertRedirect(route('password.change'));
});

it('still lands an unverified vendor on their inbox, with the verification banner shown', function () {
    $vendor = User::factory()->vendor()->unverified()->create();
    VendorProfile::factory()->for($vendor)->create();

    $this->actingAs($vendor)
        ->get('/')
        ->assertRedirect(route('vendor.inbox'));

    $this->actingAs($vendor)
        ->get(route('vendor.inbox'))
        ->assertOk()
        ->assertSee(__('auth.verification.banner'));
});
