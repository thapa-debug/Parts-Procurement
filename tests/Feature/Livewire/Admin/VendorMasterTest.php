<?php

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Livewire\Admin\VendorMaster;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization: a non-admin cannot even mount the component ------------
//
// Livewire's initial-render test harness still routes AuthorizationException
// through the real exception handler (see RequestBroker::
// temporarilyDisableExceptionHandlingAndMiddleware -- AuthorizationException
// is explicitly excluded from the "throw raw" list), so a failed mount()
// authorize() call surfaces as a 403 response, not a thrown exception here.

it('does not let a buyer mount the vendor master component', function () {
    $buyer = User::factory()->buyer()->create();

    Livewire::actingAs($buyer)->test(VendorMaster::class)->assertForbidden();
});

it('does not let a vendor mount the vendor master component', function () {
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($vendor)->test(VendorMaster::class)->assertForbidden();
});

it('lets an admin mount the vendor master component', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->assertSee(__('admin.vendor_master.title'));
});

// --- listing / search --------------------------------------------------

it('lists vendors and filters them by search', function () {
    $admin = User::factory()->admin()->create();
    $match = VendorProfile::factory()->create(['company_name' => 'Acme Dismantlers']);
    $other = VendorProfile::factory()->create(['company_name' => 'Zenith Auto Parts']);

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->assertSee('Acme Dismantlers')
        ->assertSee('Zenith Auto Parts')
        ->set('search', 'Acme')
        ->assertSee('Acme Dismantlers')
        ->assertDontSee('Zenith Auto Parts');

    expect($match->company_name)->toBe('Acme Dismantlers')
        ->and($other->company_name)->toBe('Zenith Auto Parts');
});

it('shows a guiding empty state when there are no vendors at all', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->assertSee(__('admin.vendor_master.empty'));
});

it('shows a distinct message when a search matches no vendors', function () {
    $admin = User::factory()->admin()->create();
    VendorProfile::factory()->create(['company_name' => 'Acme Dismantlers']);

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->set('search', 'Nowhere')
        ->assertSee(__('admin.vendor_master.empty_search'))
        ->assertDontSee('Acme Dismantlers');
});

it('shows an explicit Edit link to the detail page for every row, not just the clickable company name', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->assertSeeHtml('href="'.route('admin.vendors.show', $profile).'"')
        ->assertSee(__('admin.profile_edit.edit_link'));
});

it('consolidates every row\'s actions into one dropdown trigger, one per row', function () {
    $admin = User::factory()->admin()->create();
    VendorProfile::factory()->count(3)->create();

    $html = Livewire::actingAs($admin)->test(VendorMaster::class)->html();

    // aria-haspopup is unique to the dropdown trigger -- the table header
    // also happens to render the literal word "Actions" via a different
    // lang key, which would otherwise inflate a plain text count.
    expect(substr_count($html, 'aria-haspopup="true"'))->toBe(3);
});

// --- create vendor + reveal ceremony ------------------------------------

it('creates a vendor and reveals the temporary password', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('openCreateForm')
        ->set('name', 'Jane Vendor')
        ->set('email', 'jane@example.com')
        ->set('company_name', 'Acme Dismantlers')
        ->set('contact_person', 'Jane Doe')
        ->set('phone', '090-0000-0000')
        ->set('notify_email', 'jane-notify@example.com')
        ->call('createVendor')
        ->assertSet('showCreateForm', false)
        ->assertSet('revealedContext', 'created')
        ->assertSet('revealedForCompany', 'Acme Dismantlers')
        ->assertSet('revealedVerificationEmail', 'jane@example.com')
        ->assertSee('Acme Dismantlers')
        ->assertSee(__('admin.vendor_master.reveal.created_heading'))
        ->assertDontSee(__('admin.buyer_master.reveal.created_heading'))
        ->assertSee(__('admin.reveal.verification_sent', ['email' => 'jane@example.com']));

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->must_change_password)->toBeTrue()
        ->and(VendorProfile::where('user_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('clears the verification-sent notice on a password reset -- it does not apply there', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('resetPassword', $profile->id)
        ->assertSet('revealedContext', 'reset')
        ->assertSet('revealedVerificationEmail', null)
        ->assertDontSee(__('admin.reveal.verification_sent', ['email' => $profile->user->email]));
});

it('rejects an incomplete vendor creation form', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('openCreateForm')
        ->call('createVendor')
        ->assertHasErrors(['name', 'email', 'company_name', 'contact_person', 'phone', 'notify_email']);

    expect(User::where('role', UserRole::Vendor)->count())->toBe(0);
});

it('rejects a duplicate email on creation', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('openCreateForm')
        ->set('name', 'Jane Vendor')
        ->set('email', 'taken@example.com')
        ->set('company_name', 'Acme Dismantlers')
        ->set('contact_person', 'Jane Doe')
        ->set('phone', '090-0000-0000')
        ->set('notify_email', 'jane-notify@example.com')
        ->call('createVendor')
        ->assertHasErrors(['email']);
});

// --- suspend / resume ----------------------------------------------------

it('suspends and resumes a vendor from the list', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('suspend', $profile->id);

    expect($profile->fresh()->status)->toBe(VendorStatus::Suspended);

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('resume', $profile->id);

    expect($profile->fresh()->status)->toBe(VendorStatus::Active);
});

// --- reset password + reveal ceremony -----------------------------------

it('resets a vendor\'s temporary password and reveals it', function () {
    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create(['password' => Hash::make('old-password'), 'must_change_password' => false]);
    $profile = VendorProfile::factory()->for($vendor)->create(['company_name' => 'Acme Dismantlers']);

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('resetPassword', $profile->id)
        ->assertSet('revealedContext', 'reset')
        ->assertSet('revealedForCompany', 'Acme Dismantlers');

    $vendor->refresh();

    expect($vendor->must_change_password)->toBeTrue()
        ->and(Hash::check('old-password', $vendor->password))->toBeFalse();
});

it('dismisses the reveal and clears its state', function () {
    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('resetPassword', $profile->id)
        ->assertSet('revealedContext', 'reset')
        ->call('dismissReveal')
        ->assertSet('revealedPassword', null)
        ->assertSet('revealedForCompany', null)
        ->assertSet('revealedContext', null);
});

// --- email verification badge + resend ------------------------------------
//
// The `act` gate (CLAUDE.md 14) is role-agnostic -- a vendor must verify
// their email before responding to an inquiry, exactly like a buyer must
// before creating a request -- so this list needs the same badge/action a
// buyer list needs, not something vendor-specific.

it('shows a Verified badge for a verified vendor and Unverified for one who has not verified', function () {
    $admin = User::factory()->admin()->create();
    $verified = VendorProfile::factory()->create(['company_name' => 'Verified Motors']);
    $unverifiedUser = User::factory()->vendor()->unverified()->create();
    $unverified = VendorProfile::factory()->for($unverifiedUser)->create(['company_name' => 'Unverified Motors']);

    $response = Livewire::actingAs($admin)->test(VendorMaster::class);

    $response->assertSeeInOrder([$verified->company_name, __('admin.verification.verified_badge')])
        ->assertSeeInOrder([$unverified->company_name, __('admin.verification.unverified_badge')]);
});

it('only shows the resend-verification button for an unverified vendor', function () {
    $admin = User::factory()->admin()->create();
    $verified = VendorProfile::factory()->create();
    $unverifiedUser = User::factory()->vendor()->unverified()->create();
    $unverified = VendorProfile::factory()->for($unverifiedUser)->create();

    $html = Livewire::actingAs($admin)->test(VendorMaster::class)->html();

    expect(substr_count($html, __('admin.verification.resend_button')))->toBe(1);

    expect($verified->user->hasVerifiedEmail())->toBeTrue()
        ->and($unverified->user->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email for an unverified vendor', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $unverifiedUser = User::factory()->vendor()->unverified()->create();
    $profile = VendorProfile::factory()->for($unverifiedUser)->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('resendVerification', $profile->id);

    Notification::assertSentTo($unverifiedUser, VerifyEmail::class);
});

it('does not resend for an already-verified vendor', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $profile = VendorProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(VendorMaster::class)
        ->call('resendVerification', $profile->id);

    Notification::assertNothingSent();
});
