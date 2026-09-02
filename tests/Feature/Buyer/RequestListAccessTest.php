<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $this->get('/buyer/requests')->assertRedirect('/login');
});

it('forbids an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/buyer/requests')->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();

    $this->actingAs($vendor)->get('/buyer/requests')->assertForbidden();
});

it('lets a buyer in', function () {
    $buyer = User::factory()->buyer()->create();

    $this->actingAs($buyer)
        ->get('/buyer/requests')
        ->assertOk()
        ->assertSee(__('buyer.request_list.heading'));
});
