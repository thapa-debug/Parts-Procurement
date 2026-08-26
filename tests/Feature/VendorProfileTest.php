<?php

use App\Actions\ResumeVendorAction;
use App\Actions\SuspendVendorAction;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// --- model -------------------------------------------------------------

it('casts status to the VendorStatus enum and belongs to a user', function () {
    $vendor = User::factory()->vendor()->create();
    $profile = VendorProfile::factory()->for($vendor)->create();

    expect($profile->status)->toBe(VendorStatus::Active)
        ->and($profile->user->is($vendor))->toBeTrue();
});

// --- policy: isolation (CLAUDE.md 4) ------------------------------------

it('lets only the admin list the vendor master', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($admin->can('viewAny', VendorProfile::class))->toBeTrue()
        ->and($buyer->can('viewAny', VendorProfile::class))->toBeFalse()
        ->and($vendor->can('viewAny', VendorProfile::class))->toBeFalse();
});

it('lets the admin view any vendor profile and a vendor view only their own', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendorA = User::factory()->vendor()->create();
    $vendorB = User::factory()->vendor()->create();
    $profileA = VendorProfile::factory()->for($vendorA)->create();

    expect($admin->can('view', $profileA))->toBeTrue()
        ->and($vendorA->can('view', $profileA))->toBeTrue()
        ->and($vendorB->can('view', $profileA))->toBeFalse()
        ->and($buyer->can('view', $profileA))->toBeFalse();
});

it('never lets a buyer or a vendor update the vendor master (suspend/resume is admin-only)', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();
    $profile = VendorProfile::factory()->for($vendor)->create();

    expect($admin->can('update', $profile))->toBeTrue()
        ->and($vendor->can('update', $profile))->toBeFalse()
        ->and($buyer->can('update', $profile))->toBeFalse();
});

// --- suspend / resume ----------------------------------------------------

it('suspends an active vendor', function () {
    $profile = VendorProfile::factory()->create();

    app(SuspendVendorAction::class)->execute($profile);

    expect($profile->fresh()->status)->toBe(VendorStatus::Suspended);
});

it('resumes a suspended vendor', function () {
    $profile = VendorProfile::factory()->suspended()->create();

    app(ResumeVendorAction::class)->execute($profile);

    expect($profile->fresh()->status)->toBe(VendorStatus::Active);
});

it('logs vendor status changes to the activity log', function () {
    $profile = VendorProfile::factory()->create();

    app(SuspendVendorAction::class)->execute($profile);

    $activity = Activity::query()->where('subject_id', $profile->id)->where('event', 'updated')->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['status'])->toBe('suspended');
});

it('does not log a no-op suspend on an already-suspended vendor', function () {
    $profile = VendorProfile::factory()->suspended()->create();

    app(SuspendVendorAction::class)->execute($profile);

    expect(Activity::query()->where('subject_id', $profile->id)->where('event', 'updated')->count())->toBe(0);
});

it('does not log a no-op resume on an already-active vendor', function () {
    $profile = VendorProfile::factory()->create();

    app(ResumeVendorAction::class)->execute($profile);

    expect(Activity::query()->where('subject_id', $profile->id)->where('event', 'updated')->count())->toBe(0);
});
