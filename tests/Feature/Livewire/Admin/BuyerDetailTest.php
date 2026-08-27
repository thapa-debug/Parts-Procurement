<?php

use App\Livewire\Admin\BuyerDetail;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization: a non-admin cannot even mount the component ------------

it('does not let a vendor mount the buyer detail component', function () {
    $vendor = User::factory()->vendor()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($vendor)->test(BuyerDetail::class, ['buyerProfile' => $profile])->assertForbidden();
});

it('does not let the buyer themselves mount their own admin detail component', function () {
    $buyer = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->for($buyer)->create();

    Livewire::actingAs($buyer)->test(BuyerDetail::class, ['buyerProfile' => $profile])->assertForbidden();
});

it('lets an admin mount the buyer detail component with current values pre-filled', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create([
        'company_name' => 'Acme Imports',
        'phone' => '090-0000-0000',
        'default_destination_country' => 'Australia',
        'default_yard' => 'Oceania Yard',
    ]);

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->assertSee('Acme Imports')
        ->assertSet('company_name', 'Acme Imports')
        ->assertSet('phone', '090-0000-0000')
        ->assertSet('default_destination_country', 'Australia')
        ->assertSet('default_yard', 'Oceania Yard');
});

it('shows the buyer account\'s login name, email, and member code as read-only', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create(['name' => 'Jane Buyer', 'email' => 'jane@example.com']);
    $profile = BuyerProfile::factory()->for($buyer)->create(['member_code' => 'BYR-000042']);

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->assertSee('Jane Buyer')
        ->assertSee('jane@example.com')
        ->assertSee('BYR-000042');
});

// --- reusability: this page uses <x-admin.profile-edit-form> just like ------
// VendorDetail does -- assert it renders ITS OWN fields/labels, not the
// other domain's. See VendorDetailTest for the mirrored assertion. This is
// exactly the class of bug the reveal-component's "Vendor created" leak was
// -- a shared component silently showing the wrong domain's copy.

it('renders buyer-specific fields and labels, not vendor-only ones', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->assertSee(__('admin.buyer_master.create_form.default_destination_country_label'))
        ->assertSee(__('admin.buyer_master.create_form.default_yard_label'))
        ->assertDontSee(__('admin.vendor_master.create_form.contact_person_label'))
        ->assertDontSee(__('admin.vendor_master.create_form.notify_email_label'));
});

// --- saving --------------------------------------------------------------

it('saves changes to the buyer profile', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->set('company_name', 'Updated Imports')
        ->set('phone', '080-1234-5678')
        ->set('default_destination_country', 'New Zealand')
        ->set('default_yard', 'North Island Yard')
        ->call('save')
        ->assertSet('justSaved', true);

    $profile->refresh();

    expect($profile->company_name)->toBe('Updated Imports')
        ->and($profile->phone)->toBe('080-1234-5678')
        ->and($profile->default_destination_country)->toBe('New Zealand')
        ->and($profile->default_yard)->toBe('North Island Yard');
});

it('clears the saved indicator as soon as a field changes again', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->call('save')
        ->assertSet('justSaved', true)
        ->set('company_name', 'Something Else')
        ->assertSet('justSaved', false);
});

// --- validation ------------------------------------------------------------

it('rejects empty required fields', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->set('company_name', '')
        ->set('default_yard', '')
        ->call('save')
        ->assertHasErrors(['company_name', 'default_yard']);
});

it('never accepts member_code as an editable field', function () {
    expect(property_exists(BuyerDetail::class, 'member_code'))->toBeFalse();
});
