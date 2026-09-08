<?php

use App\Actions\ApproveBuyerAction;
use App\Enums\UserRole;
use App\Livewire\Admin\BuyerMaster;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

it('shows a guiding empty state when there are no buyers at all', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->assertSee(__('admin.buyer_master.empty'));
});

it('shows a distinct message when a search matches no buyers', function () {
    $admin = User::factory()->admin()->create();
    BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->set('search', 'Nowhere')
        ->assertSee(__('admin.buyer_master.empty_search'))
        ->assertDontSee('Acme Imports');
});

it('shows an explicit Edit link to the detail page for every row, not just the clickable company name', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->assertSeeHtml('href="'.route('admin.buyers.show', $profile).'"')
        ->assertSee(__('admin.profile_edit.edit_link'));
});

it('consolidates every row\'s actions into one dropdown trigger, one per row', function () {
    $admin = User::factory()->admin()->create();
    BuyerProfile::factory()->count(3)->create();

    $html = Livewire::actingAs($admin)->test(BuyerMaster::class)->html();

    // aria-haspopup is unique to the dropdown trigger -- the table header
    // also happens to render the literal word "Actions" via a different
    // lang key, which would otherwise inflate a plain text count.
    expect(substr_count($html, 'aria-haspopup="true"'))->toBe(3);
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

it('offers only active countries in the create-form dropdown, excluding inactive ones', function () {
    $admin = User::factory()->admin()->create();
    $active = Country::factory()->create(['name' => 'Active Land']);
    $inactive = Country::factory()->inactive()->create(['name' => 'Inactive Land']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->assertSee('Active Land')
        ->assertDontSee('Inactive Land');

    expect($active->is_active)->toBeTrue()
        ->and($inactive->is_active)->toBeFalse();
});

it('creates a buyer and reveals the temporary password', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->set('name', 'Jane Buyer')
        ->set('email', 'jane@example.com')
        ->set('company_name', 'Acme Imports')
        ->set('phone', '090-0000-0000')
        ->set('country_id', (string) Country::factory()->create()->id)
        ->call('createBuyer')
        ->assertSet('showCreateForm', false)
        ->assertSet('revealedContext', 'created')
        ->assertSet('revealedForCompany', 'Acme Imports')
        ->assertSet('revealedVerificationEmail', 'jane@example.com')
        ->assertSee('Acme Imports')
        ->assertSee(__('admin.buyer_master.reveal.created_heading'))
        ->assertDontSee(__('admin.vendor_master.reveal.created_heading'))
        ->assertSee(__('admin.reveal.verification_sent', ['email' => 'jane@example.com']));

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->must_change_password)->toBeTrue()
        ->and(BuyerProfile::where('user_id', $user->id)->exists())->toBeTrue()
        ->and(BuyerProfile::where('user_id', $user->id)->first()->isApproved())->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('clears the verification-sent notice on a password reset -- it does not apply there', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('resetPassword', $profile->id)
        ->assertSet('revealedContext', 'reset')
        ->assertSet('revealedVerificationEmail', null)
        ->assertDontSee(__('admin.reveal.verification_sent', ['email' => $profile->user->email]));
});

it('defaults the approve-immediately checkbox to checked', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->assertSet('approve_immediately', true);
});

it('creates a buyer pending approval when approve-immediately is unchecked, and blocks them from acting until approved', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->set('name', 'Jane Buyer')
        ->set('email', 'jane@example.com')
        ->set('company_name', 'Acme Imports')
        ->set('phone', '090-0000-0000')
        ->set('country_id', (string) Country::factory()->create()->id)
        ->set('approve_immediately', false)
        ->call('createBuyer');

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $profile = BuyerProfile::where('user_id', $user->id)->firstOrFail();

    expect($profile->isApproved())->toBeFalse();

    // Verify the account (bypassing the temp-password/must-change flow,
    // irrelevant to this check) and confirm approval is still the blocker.
    $user->markEmailAsVerified();

    expect($user->fresh()->can('act'))->toBeFalse();

    app(ApproveBuyerAction::class)->execute($profile, $admin);

    expect($user->fresh()->can('act'))->toBeTrue();
});

it('rejects an incomplete buyer creation form', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('openCreateForm')
        ->call('createBuyer')
        ->assertHasErrors([
            'name', 'email', 'company_name', 'phone', 'country_id',
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
        ->set('country_id', (string) Country::factory()->create()->id)
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

// --- email verification badge + resend ------------------------------------

it('shows a Verified badge for a verified buyer and Unverified for one who has not verified', function () {
    $admin = User::factory()->admin()->create();
    $verified = BuyerProfile::factory()->create(['company_name' => 'Verified Imports']);
    $unverifiedUser = User::factory()->buyer()->unverified()->create();
    $unverified = BuyerProfile::factory()->for($unverifiedUser)->create(['company_name' => 'Unverified Imports']);

    $response = Livewire::actingAs($admin)->test(BuyerMaster::class);

    $response->assertSeeInOrder([$verified->company_name, __('admin.verification.verified_badge')])
        ->assertSeeInOrder([$unverified->company_name, __('admin.verification.unverified_badge')]);
});

it('only shows the resend-verification button for an unverified buyer', function () {
    $admin = User::factory()->admin()->create();
    $verified = BuyerProfile::factory()->create();
    $unverifiedUser = User::factory()->buyer()->unverified()->create();
    $unverified = BuyerProfile::factory()->for($unverifiedUser)->create();

    $html = Livewire::actingAs($admin)->test(BuyerMaster::class)->html();

    expect(substr_count($html, __('admin.verification.resend_button')))->toBe(1);

    expect($verified->user->hasVerifiedEmail())->toBeTrue()
        ->and($unverified->user->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email for an unverified buyer', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $unverifiedUser = User::factory()->buyer()->unverified()->create();
    $profile = BuyerProfile::factory()->for($unverifiedUser)->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('resendVerification', $profile->id);

    Notification::assertSentTo($unverifiedUser, VerifyEmail::class);
});

it('does not resend for an already-verified buyer', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->create();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('resendVerification', $profile->id);

    Notification::assertNothingSent();
});

// --- approval badge + Approve action ----------------------------------------
//
// The authorization side of this is already covered at the policy level by
// BuyerApprovalGateTest's "lets only an admin approve a buyer profile" --
// a buyer/vendor can't reach approveBuyer() through this component at all,
// since mount() already 403s them before any action method is reachable
// (see the "does not let a buyer/vendor mount" cases above), the same
// shape already established for resetPassword/resendVerification.

it('shows an Approved badge for an approved buyer and Pending for one who is not', function () {
    $admin = User::factory()->admin()->create();
    $approved = BuyerProfile::factory()->create(['company_name' => 'Approved Imports']);
    $pending = BuyerProfile::factory()->pending()->create(['company_name' => 'Pending Imports']);

    $response = Livewire::actingAs($admin)->test(BuyerMaster::class);

    $response->assertSeeInOrder([$approved->company_name, __('admin.buyer_master.approval.approved_badge')])
        ->assertSeeInOrder([$pending->company_name, __('admin.buyer_master.approval.pending_badge')]);
});

it('only shows the Approve action for a pending buyer', function () {
    $admin = User::factory()->admin()->create();
    BuyerProfile::factory()->create(); // approved by factory default
    BuyerProfile::factory()->pending()->create();

    $html = Livewire::actingAs($admin)->test(BuyerMaster::class)->html();

    // Matching the wire:click call itself, not the visible label -- "Approve"
    // is a substring of the "Approved" badge text, so counting the label
    // would over-count.
    expect(substr_count($html, 'wire:click="approveBuyer('))->toBe(1);
});

it('approves a pending buyer via the Approve action', function () {
    $admin = User::factory()->admin()->create();
    $profile = BuyerProfile::factory()->pending()->create();

    expect($profile->isApproved())->toBeFalse();

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->call('approveBuyer', $profile->id);

    $profile->refresh();

    expect($profile->isApproved())->toBeTrue()
        ->and($profile->approved_by)->toBe($admin->id);
});

it('filters to pending-only buyers when the checkbox is checked', function () {
    $admin = User::factory()->admin()->create();
    $approved = BuyerProfile::factory()->create(['company_name' => 'Approved Imports']);
    $pending = BuyerProfile::factory()->pending()->create(['company_name' => 'Pending Imports']);

    Livewire::actingAs($admin)
        ->test(BuyerMaster::class)
        ->assertSee($approved->company_name)
        ->assertSee($pending->company_name)
        ->set('pendingOnly', true)
        ->assertDontSee($approved->company_name)
        ->assertSee($pending->company_name);
});
