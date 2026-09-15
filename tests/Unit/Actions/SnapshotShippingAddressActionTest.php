<?php

use App\Actions\SnapshotShippingAddressAction;
use App\Exceptions\ShippingAddressNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\PartRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('copies the address fields onto the part_request, including the country name, not its id', function () {
    $buyer = BuyerProfile::factory()->create();
    $partRequest = PartRequest::factory()->create(['buyer_id' => $buyer->id]);
    $country = Country::factory()->create(['name' => 'United States']);
    $address = BuyerAddress::factory()->create([
        'buyer_id' => $buyer->id,
        'country_id' => $country->id,
        'recipient_name' => 'Jane Doe',
        'phone' => '555-0100',
        'postal_code' => '90001',
        'state' => 'California',
        'city' => 'Los Angeles',
        'address_line1' => '123 Main St',
        'address_line2' => 'Apt 4',
    ]);

    $result = app(SnapshotShippingAddressAction::class)->execute($partRequest, $address);

    expect($result->shipping_address_id)->toBe($address->id)
        ->and($result->shipping_recipient_name)->toBe('Jane Doe')
        ->and($result->shipping_phone)->toBe('555-0100')
        ->and($result->shipping_postal_code)->toBe('90001')
        ->and($result->shipping_country)->toBe('United States')
        ->and($result->shipping_state)->toBe('California')
        ->and($result->shipping_city)->toBe('Los Angeles')
        ->and($result->shipping_address_line1)->toBe('123 Main St')
        ->and($result->shipping_address_line2)->toBe('Apt 4');
});

it('keeps the snapshot untouched when the source address is later edited', function () {
    $buyer = BuyerProfile::factory()->create();
    $partRequest = PartRequest::factory()->create(['buyer_id' => $buyer->id]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'city' => 'Osaka']);

    app(SnapshotShippingAddressAction::class)->execute($partRequest, $address);
    $address->update(['city' => 'Kyoto']);

    expect($partRequest->fresh()->shipping_city)->toBe('Osaka');
});

it('nulls the traceability pointer but keeps the snapshot fields when the source address is deleted', function () {
    $buyer = BuyerProfile::factory()->create();
    $partRequest = PartRequest::factory()->create(['buyer_id' => $buyer->id]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'city' => 'Nagoya']);

    app(SnapshotShippingAddressAction::class)->execute($partRequest, $address);
    $address->delete();

    $fresh = $partRequest->fresh();
    expect($fresh->shipping_address_id)->toBeNull()
        ->and($fresh->shipping_city)->toBe('Nagoya');
});

it('refuses to snapshot an address that belongs to a different buyer', function () {
    $requestBuyer = BuyerProfile::factory()->create();
    $otherBuyer = BuyerProfile::factory()->create();
    $partRequest = PartRequest::factory()->create(['buyer_id' => $requestBuyer->id]);
    $othersAddress = BuyerAddress::factory()->create(['buyer_id' => $otherBuyer->id]);

    $attempt = fn () => app(SnapshotShippingAddressAction::class)->execute($partRequest, $othersAddress);

    expect($attempt)->toThrow(ShippingAddressNotAllowedException::class);
    expect($partRequest->fresh()->shipping_address_id)->toBeNull();
});
