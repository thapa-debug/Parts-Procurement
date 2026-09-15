<?php

use App\Actions\UpdateBuyerAddressAction;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function updatedBuyerAddressData(array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Hanako Suzuki',
        'phone' => '080-9999-0000',
        'postal_code' => '100-0001',
        'country_id' => Country::factory()->create()->id,
        'state' => null,
        'city' => 'Chiyoda',
        'address_line1' => '1-1 Marunouchi',
        'address_line2' => 'Suite 500',
    ], $overrides);
}

it('updates an address\'s fields in place', function () {
    $address = BuyerAddress::factory()->create();

    $updated = app(UpdateBuyerAddressAction::class)->execute($address, updatedBuyerAddressData());

    expect($updated->recipient_name)->toBe('Hanako Suzuki')
        ->and($updated->city)->toBe('Chiyoda')
        ->and($updated->address_line2)->toBe('Suite 500');
});

it('makes this address default and unsets every other one when requested', function () {
    $buyer = BuyerProfile::factory()->create();
    $current = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => true]);
    $other = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => false]);

    $updated = app(UpdateBuyerAddressAction::class)->execute($other, updatedBuyerAddressData(['is_default' => true]));

    expect($updated->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse();
});

it('never un-defaults the current default just because is_default was omitted or false', function () {
    $address = BuyerAddress::factory()->create(['is_default' => true]);

    $updated = app(UpdateBuyerAddressAction::class)->execute($address, updatedBuyerAddressData(['is_default' => false]));

    expect($updated->is_default)->toBeTrue();
});
