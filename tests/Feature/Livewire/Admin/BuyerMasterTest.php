<?php

use App\Enums\UserRole;
use App\Livewire\Admin\BuyerMaster;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization: a non-admin cannot even mount the component ------------
//
// See VendorMasterTest for why this asserts a 403 response, not a thrown
// exception (Livewire's initial-render test harness routes
// AuthorizationException through the real exception handler).

it('does not let a buyer mount the buyer master component', function () {
    $buyer = User::factory()->buyer()->create();

    Livewire::actingAs($buyer)->test(BuyerMaster::class)->assertForbidden();
});

it('does not let a vendor mount the buyer master component', function () {
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($vendor)->test(BuyerMaster::class)->assertForbidden();
});

it('lets an admin mount the buyer master component', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->assertSee(__('admin.buyer_master.title'));
});

// --- listing / search --------------------------------------------------

it('lists buyers and filters them by search', function () {
    $admin = User::factory()->admin()->create();
    $match = BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);
    $other = BuyerProfile::factory()->create(['company_name' => 'Zenith Trading Co']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->assertSee('Acme Imports')
        ->assertSee('Zenith Trading Co')
        ->set('search', 'Acme')
        ->assertSee('Acme Imports')
        ->assertDontSee('Zenith Trading Co');

    expect($match->company_name)->toBe('Acme Imports')
        ->and($other->company_name)->toBe('Zenith Trading Co');
});

it('finds a buyer by member code', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create(['member_code' => 'BYR-000042']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->set('search', 'BYR-000042')
        ->assertSee($profile->company_name);
});

// --- create buyer + reveal ceremony --------------------------------------

it('creates a buyer and reveals the temporary password', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->set('name', 'Jane Buyer')
        ->set('email', 'jane@example.com')
        ->set('company_name', 'Acme Imports')
        ->set('phone', '090-0000-0000')
        ->set('default_destination_country', 'Australia')
        ->set('default_yard', 'Oceania Yard')
        ->call('createBuyer')
        ->assertSet('showCreateForm', false)
        ->assertSet('revealedContext', 'created')
        ->assertSet('revealedForCompany', 'Acme Imports')
        ->assertSee('Acme Imports')
        ->assertSee(__('admin.buyer_master.reveal.created_heading'))
        ->assertDontSee(__('admin.vendor_master.reveal.created_heading'));

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->must_change_password)->toBeTrue()
        ->and(BuyerProfile::where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects an incomplete buyer creation form', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->call('createBuyer')
        ->assertHasErrors([
            'name', 'email', 'company_name', 'phone',
            'default_destination_country', 'default_yard',
        ]);

    expect(User::where('role', UserRole::Buyer)->count())->toBe(0);
});

it('rejects a duplicate email on creation', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->set('name', 'Jane Buyer')
        ->set('email', 'taken@example.com')
        ->set('company_name', 'Acme Imports')
        ->set('phone', '090-0000-0000')
        ->set('default_destination_country', 'Australia')
        ->set('default_yard', 'Oceania Yard')
        ->call('createBuyer')
        ->assertHasErrors(['email']);
});

it('does not accept member_code as a create-form field', function () {
    expect(property_exists(BuyerMaster::class, 'member_code'))->toBeFalse();
});

// --- reset password + reveal ceremony -----------------------------------

it('resets a buyer\'s temporary password and reveals it', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create(['password' => Hash::make('old-password'), 'must_change_password' => false]);
    $profile = BuyerProfile::factory()->for($buyer)->create(['company_name' => 'Acme Imports']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('resetPassword', $profile->id)
        ->assertSet('revealedContext', 'reset')
        ->assertSet('revealedForCompany', 'Acme Imports');

    $buyer->refresh();

    expect($buyer->must_change_password)->toBeTrue()
        ->and(Hash::check('old-password', $buyer->password))->toBeFalse();
});

it('dismisses the reveal and clears its state', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('resetPassword', $profile->id)
        ->assertSet('revealedContext', 'reset')
        ->call('dismissReveal')
        ->assertSet('revealedPassword', null)
        ->assertSet('revealedForCompany', null)
        ->assertSet('revealedContext', null);
});
