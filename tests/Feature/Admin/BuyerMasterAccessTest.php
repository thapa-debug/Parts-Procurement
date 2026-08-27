<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $this->get('/admin/buyers')->assertRedirect('/login');
});

it('forbids a buyer', function () {
    $buyer = User::factory()->buyer()->create();

    $this->actingAs($buyer)->get('/admin/buyers')->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();

    $this->actingAs($vendor)->get('/admin/buyers')->assertForbidden();
});

it('lets an admin in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/buyers')
        ->assertOk()
        ->assertSee(__('admin.buyer_master.title'));
});
