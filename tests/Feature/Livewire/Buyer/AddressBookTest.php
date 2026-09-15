<?php

use App\Livewire\Buyer\AddressBook;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function fillAddressForm($component, array $overrides = []): void
{
    $data = array_merge([
        'recipient_name' => 'Taro Yamada',
        'phone' => '090-1234-5678',
        'postal_code' => '150-0001',
        'country_id' => (string) Country::factory()->create()->id,
        'state' => '',
        'city' => 'Shibuya',
        'address_line1' => '1-1-1 Jinnan',
        'address_line2' => '',
    ], $overrides);

    foreach ($data as $property => $value) {
        $component->set($property, $value);
    }
}

it('shows only the acting buyer\'s own saved addresses', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    BuyerAddress::factory()->create(['buyer_id' => $ownerProfile->id, 'recipient_name' => 'My Own Address']);

    $otherBuyer = User::factory()->buyer()->create();
    $otherProfile = BuyerProfile::factory()->for($otherBuyer)->create();
    BuyerAddress::factory()->create(['buyer_id' => $otherProfile->id, 'recipient_name' => 'Someone Elses Address']);

    Livewire::actingAs($owner)
        ->test(AddressBook::class)
        ->assertSee('My Own Address')
        ->assertDontSee('Someone Elses Address');
});

it('creates an address and makes it default automatically as the buyer\'s first', function () {
    $owner = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($owner)->create();

    $component = Livewire::actingAs($owner)->test(AddressBook::class)->call('startCreate');
    fillAddressForm($component);
    $component->call('save')->assertHasNoErrors();

    $address = BuyerAddress::sole();
    expect($address->recipient_name)->toBe('Taro Yamada')
        ->and($address->is_default)->toBeTrue();
});

it('does not default a second address unless explicitly marked', function () {
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);

    $component = Livewire::actingAs($owner)->test(AddressBook::class)->call('startCreate');
    fillAddressForm($component, ['is_default' => false]);
    $component->call('save')->assertHasNoErrors();

    expect(BuyerAddress::where('buyer_id', $profile->id)->where('is_default', false)->count())->toBe(1);
});

it('validates required fields before saving', function () {
    $owner = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($owner)->create();

    Livewire::actingAs($owner)
        ->test(AddressBook::class)
        ->call('startCreate')
        ->call('save')
        ->assertHasErrors(['recipient_name', 'phone', 'postal_code', 'country_id', 'city', 'address_line1']);
});

it('edits an existing address in place', function () {
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'city' => 'Osaka']);

    $component = Livewire::actingAs($owner)->test(AddressBook::class)->call('startEdit', $address->id);
    fillAddressForm($component, ['city' => 'Kyoto']);
    $component->call('save')->assertHasNoErrors();

    expect($address->fresh()->city)->toBe('Kyoto');
});

it('refuses to edit or delete another buyer\'s address', function () {
    $owner = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($owner)->create();

    $otherBuyer = User::factory()->buyer()->create();
    $otherProfile = BuyerProfile::factory()->for($otherBuyer)->create();
    $othersAddress = BuyerAddress::factory()->create(['buyer_id' => $otherProfile->id]);

    Livewire::actingAs($owner)->test(AddressBook::class)->call('startEdit', $othersAddress->id)->assertForbidden();
    Livewire::actingAs($owner)->test(AddressBook::class)->call('delete', $othersAddress->id)->assertForbidden();
});

it('deletes an address', function () {
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $address = BuyerAddress::factory()->create(['buyer_id' => $profile->id]);

    Livewire::actingAs($owner)->test(AddressBook::class)->call('delete', $address->id);

    expect(BuyerAddress::find($address->id))->toBeNull();
});

it('sets a different address as default', function () {
    $owner = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($owner)->create();
    $current = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => true]);
    $other = BuyerAddress::factory()->create(['buyer_id' => $profile->id, 'is_default' => false]);

    Livewire::actingAs($owner)->test(AddressBook::class)->call('setDefault', $other->id);

    expect($other->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse();
});
