<?php

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $this->get('/vendor/inbox')->assertRedirect('/login');
});

it('forbids a buyer', function () {
    $buyer = User::factory()->buyer()->create();

    $this->actingAs($buyer)->get('/vendor/inbox')->assertForbidden();
});

it('forbids an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/vendor/inbox')->assertForbidden();
});

it('lets a vendor in', function () {
    $vendor = User::factory()->vendor()->create();
    VendorProfile::factory()->for($vendor)->create();

    $this->actingAs($vendor)->get('/vendor/inbox')->assertOk();
});
