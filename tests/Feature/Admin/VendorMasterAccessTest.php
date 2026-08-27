<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $this->get('/admin/vendors')->assertRedirect('/login');
});

it('forbids a buyer', function () {
    $buyer = User::factory()->buyer()->create();

    $this->actingAs($buyer)->get('/admin/vendors')->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();

    $this->actingAs($vendor)->get('/admin/vendors')->assertForbidden();
});

it('lets an admin in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/vendors')
        ->assertOk()
        ->assertSee(__('admin.vendor_master.title'));
});
