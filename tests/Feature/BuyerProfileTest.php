<?php

use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- model ---------------------------------------------------------------

it('belongs to a user', function () {
    $buyer = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($buyer)->create();

    expect($profile->user->is($buyer))->toBeTrue();
});

it('belongs to a country', function () {
    $country = Country::factory()->create(['name' => 'Australia']);
    $profile = BuyerProfile::factory()->create(['country_id' => $country->id]);

    expect($profile->country->name)->toBe('Australia');
});

it('generates a member code from the user id', function () {
    $buyer = User::factory()->buyer()->create();

    expect(BuyerProfile::generateMemberCode($buyer))->toBe('BYR-'.str_pad((string) $buyer->id, 6, '0', STR_PAD_LEFT));
});

// --- policy: isolation (CLAUDE.md 4) ---------------------------------------

it('lets only the admin list the buyer master', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($admin->can('viewAny', BuyerProfile::class))->toBeTrue()
        ->and($buyer->can('viewAny', BuyerProfile::class))->toBeFalse()
        ->and($vendor->can('viewAny', BuyerProfile::class))->toBeFalse();
});

it('lets only the admin create a buyer profile via the admin-created path', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($admin->can('create', BuyerProfile::class))->toBeTrue()
        ->and($buyer->can('create', BuyerProfile::class))->toBeFalse()
        ->and($vendor->can('create', BuyerProfile::class))->toBeFalse();
});

it('lets the admin view any buyer profile and a buyer view only their own', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();
    $buyerA = User::factory()->buyer()->create();
    $buyerB = User::factory()->buyer()->create();
    $profileA = BuyerProfile::factory()->for($buyerA)->create();

    expect($admin->can('view', $profileA))->toBeTrue()
        ->and($buyerA->can('view', $profileA))->toBeTrue()
        ->and($buyerB->can('view', $profileA))->toBeFalse()
        ->and($vendor->can('view', $profileA))->toBeFalse();
});

it('never lets a buyer or a vendor update the buyer master', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();
    $buyer = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($buyer)->create();

    expect($admin->can('update', $profile))->toBeTrue()
        ->and($buyer->can('update', $profile))->toBeFalse()
        ->and($vendor->can('update', $profile))->toBeFalse();
});
