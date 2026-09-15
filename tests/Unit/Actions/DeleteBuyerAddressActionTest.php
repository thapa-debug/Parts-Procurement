<?php

use App\Actions\DeleteBuyerAddressAction;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes a non-default address without touching the default', function () {
    $buyer = BuyerProfile::factory()->create();
    $default = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => true]);
    $other = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => false]);

    app(DeleteBuyerAddressAction::class)->execute($other);

    expect(BuyerAddress::find($other->id))->toBeNull()
        ->and($default->fresh()->is_default)->toBeTrue();
});

it('promotes the most recently created remaining address to default when the default is deleted', function () {
    $buyer = BuyerProfile::factory()->create();
    $older = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => false]);
    $default = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => true]);
    $newest = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => false]);

    app(DeleteBuyerAddressAction::class)->execute($default);

    expect($newest->fresh()->is_default)->toBeTrue()
        ->and($older->fresh()->is_default)->toBeFalse();
});

it('leaves no default when the buyer\'s only address is deleted', function () {
    $buyer = BuyerProfile::factory()->create();
    $only = BuyerAddress::factory()->create(['buyer_id' => $buyer->id, 'is_default' => true]);

    app(DeleteBuyerAddressAction::class)->execute($only);

    expect(BuyerAddress::where('buyer_id', $buyer->id)->count())->toBe(0);
});
