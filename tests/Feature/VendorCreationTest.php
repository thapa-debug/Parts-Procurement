<?php

use App\Actions\CreateVendorAction;
use App\Actions\ResetTemporaryPasswordAction;
use App\Enums\UserRole;
use App\Http\Requests\CreateVendorRequest;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function vendorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Vendor',
        'email' => 'jane@example.com',
        'company_name' => 'Acme Dismantlers',
        'contact_person' => 'Jane Doe',
        'phone' => '090-0000-0000',
        'notify_email' => 'jane-notify@example.com',
    ], $overrides);
}

// --- CreateVendorAction: happy path -----------------------------------------

it('creates a user and vendor profile together, atomically', function () {
    Notification::fake();

    $payload = vendorPayload();

    $result = app(CreateVendorAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['company_name'],
        $payload['contact_person'],
        $payload['phone'],
        $payload['notify_email'],
    );

    expect($result['user']->role)->toBe(UserRole::Vendor)
        ->and($result['user']->must_change_password)->toBeTrue()
        ->and($result['user']->hasVerifiedEmail())->toBeFalse()
        ->and($result['vendor_profile']->user_id)->toBe($result['user']->id)
        ->and($result['vendor_profile']->company_name)->toBe('Acme Dismantlers')
        ->and(Hash::check($result['temporary_password'], $result['user']->password))->toBeTrue();

    expect(User::count())->toBe(1)
        ->and(VendorProfile::count())->toBe(1);

    // Auto-sent on creation (CLAUDE.md §14) -- the account still starts
    // unverified (asserted above); this only removes the admin's manual
    // "Resend verification" click as the default path.
    Notification::assertSentTo($result['user'], VerifyEmail::class);
    Notification::assertCount(1);
});

// --- CreateVendorAction: transaction rollback -------------------------------

it('rolls back the entire transaction and creates zero users if the vendor profile write fails', function () {
    Notification::fake();

    VendorProfile::creating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $payload = vendorPayload();

    $attempt = fn () => app(CreateVendorAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['company_name'],
        $payload['contact_person'],
        $payload['phone'],
        $payload['notify_email'],
    );

    expect($attempt)->toThrow(RuntimeException::class);

    expect(User::count())->toBe(0)
        ->and(VendorProfile::count())->toBe(0);

    // The verification send happens after the transaction commits -- a
    // rolled-back creation must never have emailed anyone.
    Notification::assertNothingSent();
});

// --- CreateVendorRequest: validation -----------------------------------------

it('requires every vendor creation field', function () {
    $validator = Validator::make([], (new CreateVendorRequest)->rules());

    expect($validator->fails())->toBeTrue();

    foreach (['name', 'email', 'company_name', 'contact_person', 'phone', 'notify_email'] as $field) {
        expect($validator->errors()->has($field))->toBeTrue();
    }
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $validator = Validator::make(
        vendorPayload(['email' => 'taken@example.com']),
        (new CreateVendorRequest)->rules(),
    );

    expect($validator->errors()->has('email'))->toBeTrue();
});

it('passes with a complete, unique payload', function () {
    $validator = Validator::make(vendorPayload(), (new CreateVendorRequest)->rules());

    expect($validator->fails())->toBeFalse();
});

// --- CreateVendorRequest: authorization --------------------------------------

it('authorizes vendor creation for an admin only', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    foreach ([[$admin, true], [$buyer, false], [$vendor, false]] as [$user, $expected]) {
        $request = new CreateVendorRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBe($expected);
    }
});

// --- ResetTemporaryPasswordAction -------------------------------------------

it('issues a new temporary password and forces another change', function () {
    $vendor = User::factory()->vendor()->create(['must_change_password' => false]);
    $originalHash = $vendor->password;

    $result = app(ResetTemporaryPasswordAction::class)->execute($vendor);

    expect($result['user']->must_change_password)->toBeTrue()
        ->and($result['user']->password)->not->toBe($originalHash)
        ->and(Hash::check($result['temporary_password'], $result['user']->password))->toBeTrue();
});
