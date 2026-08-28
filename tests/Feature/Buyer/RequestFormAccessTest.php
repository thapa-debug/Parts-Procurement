<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $this->get('/buyer/requests/new')->assertRedirect('/login');
});

it('forbids an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/buyer/requests/new')->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();

    $this->actingAs($vendor)->get('/buyer/requests/new')->assertForbidden();
});

it('lets a buyer in', function () {
    $buyer = User::factory()->buyer()->create();

    $this->actingAs($buyer)
        ->get('/buyer/requests/new')
        ->assertOk()
        ->assertSee(__('buyer.request_form.heading'));
});
