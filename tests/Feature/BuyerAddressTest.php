<?php

use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- model ---------------------------------------------------------------

it('belongs to a buyer profile', function () {
    $buyer = BuyerProfile::factory()->create();
    $address = BuyerAddress::factory()->create(['buyer_id' => $buyer->id]);

    expect($address->buyer->is($buyer))->toBeTrue();
});

it('belongs to a country', function () {
    $country = Country::factory()->create(['name' => 'Australia']);
    $address = BuyerAddress::factory()->create(['country_id' => $country->id]);

    expect($address->country->name)->toBe('Australia');
});

it('exposes the buyer profile\'s default address', function () {
    $buyer = BuyerProfile::factory()->create();
    BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => false]);
    $default = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => true]);

    expect($buyer->fresh()->defaultAddress?->is($default))->toBeTrue();
});

// --- policy: isolation (CLAUDE.md 4, 9) -----------------------------------

it('lets only a buyer list their own address book', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($buyer->can('viewAny', BuyerAddress::class))->toBeTrue()
        ->and($admin->can('viewAny', BuyerAddress::class))->toBeFalse()
        ->and($vendor->can('viewAny', BuyerAddress::class))->toBeFalse();
});

it('lets a buyer view only their own saved addresses, never another buyer\'s, admin\'s, or vendor\'s', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();
    $buyerA = User::factory()->buyer()->create();
    $buyerB = User::factory()->buyer()->create();
    $profileA = BuyerProfile::factory()->for($buyerA)->create();
    $addressA = BuyerAddress::factory()->create(['buyer_id' => $profileA->id]);

    expect($buyerA->can('view', $addressA))->toBeTrue()
        ->and($buyerB->can('view', $addressA))->toBeFalse()
        ->and($admin->can('view', $addressA))->toBeFalse()
        ->and($vendor->can('view', $addressA))->toBeFalse();
});

it('lets only a buyer create an address', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($buyer->can('create', BuyerAddress::class))->toBeTrue()
        ->and($admin->can('create', BuyerAddress::class))->toBeFalse()
        ->and($vendor->can('create', BuyerAddress::class))->toBeFalse();
});

it('lets a buyer update or delete only their own address, with no admin bypass', function () {
    $admin = User::factory()->admin()->create();
    $buyerA = User::factory()->buyer()->create();
    $buyerB = User::factory()->buyer()->create();
    $profileA = BuyerProfile::factory()->for($buyerA)->create();
    $addressA = BuyerAddress::factory()->create(['buyer_id' => $profileA->id]);

    expect($buyerA->can('update', $addressA))->toBeTrue()
        ->and($buyerA->can('delete', $addressA))->toBeTrue()
        ->and($buyerB->can('update', $addressA))->toBeFalse()
        ->and($buyerB->can('delete', $addressA))->toBeFalse()
        ->and($admin->can('update', $addressA))->toBeFalse()
        ->and($admin->can('delete', $addressA))->toBeFalse();
});
