<?php

use App\Actions\CreateBuyerAction;
use App\Enums\UserRole;
use App\Http\Requests\CreateBuyerRequest;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function buyerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Buyer',
        'email' => 'jane@example.com',
        'company_name' => 'Acme Imports',
        'country_id' => Country::factory()->create()->id,
        'phone' => '090-0000-0000',
        'approve_immediately' => true,
    ], $overrides);
}

// --- CreateBuyerAction: happy path -------------------------------------

it('creates a user and buyer profile together, atomically, approved immediately when the flag is true', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $payload = buyerPayload();

    $result = app(CreateBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
        $admin,
        $payload['approve_immediately'],
    );

    expect($result['user']->role)->toBe(UserRole::Buyer)
        ->and($result['user']->must_change_password)->toBeTrue()
        ->and($result['user']->hasVerifiedEmail())->toBeFalse()
        ->and($result['buyer_profile']->user_id)->toBe($result['user']->id)
        ->and($result['buyer_profile']->company_name)->toBe('Acme Imports')
        ->and($result['buyer_profile']->member_code)->toBe(BuyerProfile::generateMemberCode($result['user']))
        ->and(Hash::check($result['temporary_password'], $result['user']->password))->toBeTrue();

    expect(User::count())->toBe(2) // the admin + the new buyer
        ->and(BuyerProfile::count())->toBe(1);

    expect($result['buyer_profile']->isApproved())->toBeTrue()
        ->and($result['buyer_profile']->approved_by)->toBe($admin->id);

    // Auto-sent on creation regardless of approve_immediately (CLAUDE.md
    // §14) -- verification and approval are fully independent conditions.
    Notification::assertSentTo($result['user'], VerifyEmail::class);
    Notification::assertCount(1);
});

it('creates a buyer profile pending approval when the flag is false', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $payload = buyerPayload(['approve_immediately' => false]);

    $result = app(CreateBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
        $admin,
        $payload['approve_immediately'],
    );

    expect($result['buyer_profile']->isApproved())->toBeFalse()
        ->and($result['buyer_profile']->approved_at)->toBeNull()
        ->and($result['buyer_profile']->approved_by)->toBeNull();

    // Verification still auto-sends regardless of the approval flag.
    Notification::assertSentTo($result['user'], VerifyEmail::class);
});

// --- CreateBuyerAction: transaction rollback ----------------------------

it('rolls back the entire transaction and creates zero users if the buyer profile write fails', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    BuyerProfile::creating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $payload = buyerPayload();

    $attempt = fn () => app(CreateBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
        $admin,
        $payload['approve_immediately'],
    );

    expect($attempt)->toThrow(RuntimeException::class);

    expect(User::count())->toBe(1) // just the admin -- the attempted buyer rolled back
        ->and(BuyerProfile::count())->toBe(0);

    // The verification send happens after the transaction commits -- a
    // rolled-back creation must never have emailed anyone.
    Notification::assertNothingSent();
});

// --- CreateBuyerRequest: validation --------------------------------------

it('requires every admin-created buyer field', function () {
    $validator = Validator::make([], (new CreateBuyerRequest)->rules());

    expect($validator->fails())->toBeTrue();

    foreach (['name', 'email', 'company_name', 'country_id', 'phone', 'approve_immediately'] as $field) {
        expect($validator->errors()->has($field))->toBeTrue();
    }
});

it('does not accept member_code as an input field', function () {
    expect((new CreateBuyerRequest)->rules())->not->toHaveKey('member_code');
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $validator = Validator::make(
        buyerPayload(['email' => 'taken@example.com']),
        (new CreateBuyerRequest)->rules(),
    );

    expect($validator->errors()->has('email'))->toBeTrue();
});

it('passes with a complete, unique payload', function () {
    $validator = Validator::make(buyerPayload(), (new CreateBuyerRequest)->rules());

    expect($validator->fails())->toBeFalse();
});

it('rejects an inactive country -- only active countries are a valid pick for a new buyer', function () {
    $inactive = Country::factory()->inactive()->create();

    $validator = Validator::make(
        buyerPayload(['country_id' => $inactive->id]),
        (new CreateBuyerRequest)->rules(),
    );

    expect($validator->errors()->has('country_id'))->toBeTrue();
});

// --- CreateBuyerRequest: authorization ------------------------------------

it('authorizes admin-created buyer creation for an admin only', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    foreach ([[$admin, true], [$buyer, false], [$vendor, false]] as [$user, $expected]) {
        $request = new CreateBuyerRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBe($expected);
    }
});
