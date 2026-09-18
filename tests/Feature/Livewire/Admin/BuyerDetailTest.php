<?php

use App\Livewire\Admin\BuyerDetail;
use App\Models\BuyerProfile;
use App\Models\Country;
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
    $country = Country::factory()->create();
    $profile = BuyerProfile::factory()->create([
        'company_name' => 'Acme Imports',
        'phone' => '090-0000-0000',
        'country_id' => $country->id,
    ]);

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->assertSee('Acme Imports')
        ->assertSet('company_name', 'Acme Imports')
        ->assertSet('phone', '090-0000-0000')
        ->assertSet('country_id', (string) $country->id);
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
        ->assertSee(__('admin.buyer_master.create_form.country_label'))
        ->assertDontSee(__('admin.vendor_master.create_form.contact_person_label'))
        ->assertDontSee(__('admin.vendor_master.create_form.notify_email_label'));
});

// --- saving --------------------------------------------------------------

it('saves changes to the buyer profile, including switching to a different country', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();
    $newCountry = Country::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->set('company_name', 'Updated Imports')
        ->set('phone', '080-1234-5678')
        ->set('country_id', (string) $newCountry->id)
        ->call('save')
        ->assertDispatched('toast', message: __('admin.profile_edit.saved'), type: 'success');

    $profile->refresh();

    expect($profile->company_name)->toBe('Updated Imports')
        ->and($profile->phone)->toBe('080-1234-5678')
        ->and($profile->country_id)->toBe($newCountry->id);
});

it('keeps a since-deactivated country accepted when the rest of the form is resubmitted unchanged', function () {
    $admin = User::factory()->admin()->create();
    $country = Country::factory()->inactive()->create();
    $profile = BuyerProfile::factory()->create(['country_id' => $country->id]);

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->assertSet('country_id', (string) $country->id)
        ->set('phone', '080-9999-0000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: __('admin.profile_edit.saved'), type: 'success');

    expect($profile->fresh()->country_id)->toBe($country->id);
});

// --- validation ------------------------------------------------------------

it('rejects empty required fields', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerDetail::class, ['buyerProfile' => $profile])
        ->set('company_name', '')
        ->set('phone', '')
        ->call('save')
        ->assertHasErrors(['company_name', 'phone']);
});

it('never accepts member_code as an editable field', function () {
    expect(property_exists(BuyerDetail::class, 'member_code'))->toBeFalse();
});
