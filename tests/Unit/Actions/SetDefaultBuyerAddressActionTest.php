<?php

use App\Actions\SetDefaultBuyerAddressAction;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks the given address default and unsets every other one for the same buyer', function () {
    $buyer = BuyerProfile::factory()->create();
    $current = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => true]);
    $other = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => false]);

    $result = app(SetDefaultBuyerAddressAction::class)->execute($other);

    expect($result->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse();
});

it('does not affect another buyer\'s default address', function () {
    $buyerA = BuyerProfile::factory()->create();
    $buyerB = BuyerProfile::factory()->create();
    $addressA = BuyerAddress::factory()->create(['buyer_id' => $buyerA->id, 'is_default' => false]);
    $defaultB = BuyerAddress::factory()->create(['buyer_id' => $buyerB->id, 'is_default' => true]);

    app(SetDefaultBuyerAddressAction::class)->execute($addressA);

    expect($defaultB->fresh()->is_default)->toBeTrue();
});
