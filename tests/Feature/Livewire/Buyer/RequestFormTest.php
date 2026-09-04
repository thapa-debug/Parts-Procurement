<?php

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Livewire\Buyer\RequestForm;
use App\Models\BuyerProfile;
use App\Models\Maker;
use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makerNamed(string $name): Maker
{
    return Maker::factory()->create(['name' => $name]);
}

function actingBuyer(bool $verified = true, bool $approved = true): User
{
    $user = $verified
        ? User::factory()->buyer()->create()
        : User::factory()->buyer()->unverified()->create();

    $profileFactory = BuyerProfile::factory()->for($user);

    if (! $approved) {
        $profileFactory = $profileFactory->pending();
    }

    $profileFactory->create();

    return $user;
}

// --- blocked states ----------------------------------------------------

it('shows an unverified-blocked message and hides the form for an unverified buyer', function () {
    $buyer = actingBuyer(verified: false, approved: true);

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->assertSet('blockedReason', 'unverified')
        ->assertSee(__('buyer.request_form.blocked.unverified_heading'))
        ->assertDontSee(__('buyer.request_form.submit'));
});

it('shows an unapproved-blocked message and hides the form for a verified but unapproved buyer', function () {
    $buyer = actingBuyer(verified: true, approved: false);

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->assertSet('blockedReason', 'unapproved')
        ->assertSee(__('buyer.request_form.blocked.unapproved_heading'))
        ->assertDontSee(__('buyer.request_form.submit'));
});

it('prioritizes the unverified message when a buyer is both unverified and unapproved', function () {
    $buyer = actingBuyer(verified: false, approved: false);

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->assertSet('blockedReason', 'unverified');
});

it('shows the real form for a verified and approved buyer', function () {
    $buyer = actingBuyer();

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->assertSet('blockedReason', null)
        ->assertSee(__('buyer.request_form.submit'));
});

// --- authorization -----------------------------------------------------

it('does not let a vendor or admin mount the request form', function () {
    $vendor = User::factory()->vendor()->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($vendor)->test(RequestForm::class)->assertForbidden();
    Livewire::actingAs($admin)->test(RequestForm::class)->assertForbidden();
});

// --- submission ----------------------------------------------------------

it('submits a request and generates a sequential request code', function () {
    $buyer = actingBuyer();
    $toyota = makerNamed('Toyota');

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Used->value)
        ->set('maker_id', (string) $toyota->id)
        ->set('car_model', 'Crown')
        ->set('vin', 'GRS184-0002255')
        ->set('part_name', 'Right LED headlight')
        ->call('submit')
        ->assertHasNoErrors();

    $request = PartRequest::firstOrFail();

    expect($request->part_type)->toBe(PartType::Used)
        ->and($request->maker_id)->toBe($toyota->id)
        ->and($request->car_model)->toBe('Crown')
        ->and($request->vin)->toBe('GRS184-0002255')
        ->and($request->part_name)->toBe('Right LED headlight')
        ->and($request->status)->toBe(RequestStatus::New)
        ->and($request->request_code)->toBe(PartRequest::generateRequestCode($request->id))
        ->and($request->buyer_id)->toBe($buyer->buyerProfile->id);
});

it('resets the form and shows an inline confirmation after submitting', function () {
    $buyer = actingBuyer();
    $nissan = makerNamed('Nissan');

    $component = Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Both->value)
        ->set('maker_id', (string) $nissan->id)
        ->set('car_model', 'Skyline')
        ->set('vin', 'BNR34-123456')
        ->set('oem_part_number', '81110-60M00')
        ->set('part_name', 'Rear bumper')
        ->call('submit')
        ->assertSet('part_type', '')
        ->assertSet('maker_id', '')
        ->assertSet('car_model', '')
        ->assertSet('part_name', '');

    $request = PartRequest::firstOrFail();

    $component
        ->assertSet('submittedCode', $request->request_code)
        ->assertSee(__('buyer.request_form.submitted', ['code' => $request->request_code]));
});

it('clears the confirmation as soon as the buyer starts a new request', function () {
    $buyer = actingBuyer();
    $toyota = makerNamed('Toyota');
    $nissan = makerNamed('Nissan');

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Used->value)
        ->set('maker_id', (string) $toyota->id)
        ->set('car_model', 'Crown')
        ->set('vin', 'GRS184-0002255')
        ->set('part_name', 'Headlight')
        ->call('submit')
        ->assertSet('submittedCode', fn ($code) => $code !== null)
        ->set('maker_id', (string) $nissan->id)
        ->assertSet('submittedCode', null);
});

it('rejects an incomplete submission', function () {
    $buyer = actingBuyer();

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->call('submit')
        ->assertHasErrors(['part_type', 'maker_id', 'car_model', 'part_name', 'vin']);

    expect(PartRequest::count())->toBe(0);
});

it('rejects a submission without a vin, even with oem_part_number and reference_url both present', function () {
    $buyer = actingBuyer();
    $toyota = makerNamed('Toyota');

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Used->value)
        ->set('maker_id', (string) $toyota->id)
        ->set('car_model', 'Crown')
        ->set('part_name', 'Headlight')
        ->set('oem_part_number', '81110-60M00')
        ->set('reference_url', 'https://example.com/listing')
        ->call('submit')
        ->assertHasErrors(['vin']);

    expect(PartRequest::count())->toBe(0);
});

it('accepts a submission with a vin and no oem_part_number or reference_url', function () {
    $buyer = actingBuyer();
    $toyota = makerNamed('Toyota');

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Used->value)
        ->set('maker_id', (string) $toyota->id)
        ->set('car_model', 'Crown')
        ->set('part_name', 'Headlight')
        ->set('vin', 'GRS184-0002255')
        ->call('submit')
        ->assertHasNoErrors();

    expect(PartRequest::count())->toBe(1);
});

it('offers only active makers in the dropdown, excluding inactive ones', function () {
    $buyer = actingBuyer();
    makerNamed('Toyota');
    Maker::factory()->inactive()->create(['name' => 'Retired Motors']);

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->assertSee('Toyota')
        ->assertDontSee('Retired Motors');
});

it('rejects an inactive maker -- only active makers are a valid pick for a new request', function () {
    $buyer = actingBuyer();
    $inactive = Maker::factory()->inactive()->create();

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Used->value)
        ->set('maker_id', (string) $inactive->id)
        ->set('car_model', 'Crown')
        ->set('part_name', 'Headlight')
        ->set('vin', 'GRS184-0002255')
        ->call('submit')
        ->assertHasErrors(['maker_id']);

    expect(PartRequest::count())->toBe(0);
});

it('blocks submission for an unapproved buyer even if the form were somehow reached', function () {
    $buyer = actingBuyer(verified: true, approved: false);
    $toyota = makerNamed('Toyota');

    Livewire::actingAs($buyer)
        ->test(RequestForm::class)
        ->set('part_type', PartType::Used->value)
        ->set('maker_id', (string) $toyota->id)
        ->set('car_model', 'Crown')
        ->set('part_name', 'Headlight')
        ->call('submit')
        ->assertForbidden();

    expect(PartRequest::count())->toBe(0);
});
