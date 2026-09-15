<?php

use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest to login', function () {
    $this->get('/buyer/addresses')->assertRedirect('/login');
});

it('forbids an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/buyer/addresses')->assertForbidden();
});

it('forbids a vendor', function () {
    $vendor = User::factory()->vendor()->create();

    $this->actingAs($vendor)->get('/buyer/addresses')->assertForbidden();
});

it('lets a buyer in', function () {
    $buyer = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($buyer)->create();

    $this->actingAs($buyer)->get('/buyer/addresses')->assertOk();
});
