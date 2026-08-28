<?php

use App\Actions\ApproveBuyerAction;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// --- the 'act' gate: verified email is necessary but not sufficient for a
// buyer -- CLAUDE.md §14's new approval gate sits on top of it. -----------

it('blocks a verified but unapproved buyer from acting', function () {
    $user = User::factory()->buyer()->create();
    BuyerProfile::factory()->pending()->for($user)->create();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(Gate::forUser($user)->allows('act'))->toBeFalse();
});

it('allows a verified and approved buyer to act', function () {
    $user = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($user)->create(); // factory default: approved

    expect(Gate::forUser($user)->allows('act'))->toBeTrue();
});

it('blocks an unverified buyer from acting even if somehow already approved', function () {
    $user = User::factory()->buyer()->unverified()->create();
    BuyerProfile::factory()->for($user)->create(); // approved

    expect(Gate::forUser($user)->allows('act'))->toBeFalse();
});

it('blocks a buyer with no profile at all from acting', function () {
    // Shouldn't happen in practice (both creation paths always create the
    // profile alongside the user), but the gate must not crash or default
    // to allowing when buyerProfile is null.
    $user = User::factory()->buyer()->create();

    expect(Gate::forUser($user)->allows('act'))->toBeFalse();
});

it('is unaffected by buyer approval for a verified vendor', function () {
    $user = User::factory()->vendor()->create();

    expect(Gate::forUser($user)->allows('act'))->toBeTrue();
});

// --- ApproveBuyerAction ----------------------------------------------------

it('approves a pending buyer and records who approved them', function () {
    $admin = User::factory()->admin()->create();
    $buyerUser = User::factory()->buyer()->create();
    $profile = BuyerProfile::factory()->pending()->for($buyerUser)->create();

    expect($profile->isApproved())->toBeFalse();

    $approved = app(ApproveBuyerAction::class)->execute($profile, $admin);

    expect($approved->isApproved())->toBeTrue()
        ->and($approved->approved_by)->toBe($admin->id)
        ->and($approved->approved_at)->not->toBeNull();

    // Approving is an auditable action (CLAUDE.md §11).
    expect(Activity::where('subject_id', $profile->id)->where('subject_type', BuyerProfile::class)->exists())
        ->toBeTrue();
});

// --- BuyerProfilePolicy::approve --------------------------------------------

it('lets only an admin approve a buyer profile', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();
    $profile = BuyerProfile::factory()->pending()->create();

    expect($admin->can('approve', $profile))->toBeTrue()
        ->and($buyer->can('approve', $profile))->toBeFalse()
        ->and($vendor->can('approve', $profile))->toBeFalse();
});
