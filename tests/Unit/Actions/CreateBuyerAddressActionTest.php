<?php

use App\Actions\CreateBuyerAddressAction;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buyerAddressData(array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Taro Yamada',
        'phone' => '090-1234-5678',
        'postal_code' => '150-0001',
        'country_id' => Country::factory()->create()->id,
        'state' => null,
        'city' => 'Shibuya',
        'address_line1' => '1-1-1 Jinnan',
        'address_line2' => null,
    ], $overrides);
}

it('makes a buyer\'s very first address the default regardless of what was passed', function () {
    $buyer = BuyerProfile::factory()->create();

    $address = app(CreateBuyerAddressAction::class)->execute($buyer, buyerAddressData(['is_default' => false]));

    expect($address->is_default)->toBeTrue()
        ->and($address->buyer_id)->toBe($buyer->id)
        ->and($address->recipient_name)->toBe('Taro Yamada');
});

it('does not default a second address unless explicitly requested', function () {
    $buyer = BuyerProfile::factory()->create();
    $first = app(CreateBuyerAddressAction::class)->execute($buyer, buyerAddressData());

    $second = app(CreateBuyerAddressAction::class)->execute($buyer, buyerAddressData(['is_default' => false]));

    expect($second->is_default)->toBeFalse()
        ->and($first->fresh()->is_default)->toBeTrue();
});

it('unsets every other address when a new one is explicitly marked default', function () {
    $buyer = BuyerProfile::factory()->create();
    $first = app(CreateBuyerAddressAction::class)->execute($buyer, buyerAddressData());

    $second = app(CreateBuyerAddressAction::class)->execute($buyer, buyerAddressData(['is_default' => true]));

    expect($second->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse()
        ->and(BuyerAddress::where('buyer_id', $buyer->id)->where('is_default', true)->count())->toBe(1);
});
