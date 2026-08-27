<?php

use App\Livewire\Admin\VendorDetail;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization: a non-admin cannot even mount the component ------------
//
// Gated on 'update' (not 'view') deliberately -- see VendorDetail::mount().
// A vendor viewing their OWN profile would pass 'view' but must still be
// blocked here, since this is the admin-only edit screen.

it('does not let a buyer mount the vendor detail component', function () {
    $buyer = User::factory()->buyer()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($buyer)->test(VendorDetail::class, ['vendorProfile' => $profile])->assertForbidden();
});

it('does not let the vendor themselves mount their own admin detail component', function () {
    $vendor = User::factory()->vendor()->create();
    $profile = VendorProfile::factory()->for($vendor)->create();

    Livewire::actingAs($vendor)->test(VendorDetail::class, ['vendorProfile' => $profile])->assertForbidden();
});

it('lets an admin mount the vendor detail component with current values pre-filled', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create([
        'company_name' => 'Acme Dismantlers',
        'contact_person' => 'Jane Doe',
        'phone' => '090-0000-0000',
        'notify_email' => 'jane-notify@example.com',
    ]);

    Livewire::actingAs($admin)
        ->test(VendorDetail::class, ['vendorProfile' => $profile])
        ->assertSee('Acme Dismantlers')
        ->assertSet('company_name', 'Acme Dismantlers')
        ->assertSet('contact_person', 'Jane Doe')
        ->assertSet('phone', '090-0000-0000')
        ->assertSet('notify_email', 'jane-notify@example.com');
});

it('shows the vendor account\'s login name and email as read-only', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create(['name' => 'Jane Vendor', 'email' => 'jane@example.com']);
    $profile = VendorProfile::factory()->for($vendor)->create();

    Livewire::actingAs($admin)
        ->test(VendorDetail::class, ['vendorProfile' => $profile])
        ->assertSee('Jane Vendor')
        ->assertSee('jane@example.com');
});

// --- reusability: this page uses <x-admin.profile-edit-form> just like ------
// BuyerDetail does -- assert it renders ITS OWN fields/labels, not the
// other domain's. See BuyerDetailTest for the mirrored assertion.

it('renders vendor-specific fields and labels, not buyer-only ones', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorDetail::class, ['vendorProfile' => $profile])
        ->assertSee(__('admin.vendor_master.create_form.contact_person_label'))
        ->assertSee(__('admin.vendor_master.create_form.notify_email_label'))
        ->assertDontSee(__('admin.buyer_master.create_form.default_yard_label'))
        ->assertDontSee(__('admin.buyer_master.create_form.default_destination_country_label'))
        ->assertDontSee(__('admin.buyer_master.table.member_code'));
});

// --- saving --------------------------------------------------------------

it('saves changes to the vendor profile', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorDetail::class, ['vendorProfile' => $profile])
        ->set('company_name', 'Updated Motors')
        ->set('contact_person', 'New Contact')
        ->set('phone', '080-1234-5678')
        ->set('notify_email', 'updated-notify@example.com')
        ->call('save')
        ->assertSet('justSaved', true);

    $profile->refresh();

    expect($profile->company_name)->toBe('Updated Motors')
        ->and($profile->contact_person)->toBe('New Contact')
        ->and($profile->phone)->toBe('080-1234-5678')
        ->and($profile->notify_email)->toBe('updated-notify@example.com');
});

it('clears the saved indicator as soon as a field changes again', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorDetail::class, ['vendorProfile' => $profile])
        ->call('save')
        ->assertSet('justSaved', true)
        ->set('company_name', 'Something Else')
        ->assertSet('justSaved', false);
});

// --- validation ------------------------------------------------------------

it('rejects empty required fields', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorDetail::class, ['vendorProfile' => $profile])
        ->set('company_name', '')
        ->set('notify_email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['company_name', 'notify_email']);
});
