<?php

use App\Actions\RegisterBuyerAction;
use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function registerBuyerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Buyer',
        'email' => 'jane@example.com',
        'password' => 'my-strong-password1',
        'company_name' => 'Acme Imports',
        'country_id' => Country::factory()->create()->id,
        'phone' => '090-0000-0000',
    ], $overrides);
}

it('creates a user and buyer profile, with a self-chosen password and no forced change', function () {
    Notification::fake();

    $payload = registerBuyerPayload();

    $result = app(RegisterBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['password'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
    );

    expect($result['user']->role)->toBe(UserRole::Buyer)
        ->and($result['user']->must_change_password)->toBeFalse()
        ->and($result['user']->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('my-strong-password1', $result['user']->password))->toBeTrue()
        ->and($result['buyer_profile']->member_code)->toBe(BuyerProfile::generateMemberCode($result['user']));

    expect(User::count())->toBe(1)
        ->and(BuyerProfile::count())->toBe(1);
});

it('sends a verification email notification on registration', function () {
    Notification::fake();

    $payload = registerBuyerPayload();

    $result = app(RegisterBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['password'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
    );

    Notification::assertSentTo($result['user'], VerifyEmail::class);
});

it('rolls back the whole transaction if the buyer profile write fails', function () {
    Notification::fake();

    BuyerProfile::creating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $payload = registerBuyerPayload();

    $attempt = fn () => app(RegisterBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['password'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
    );

    expect($attempt)->toThrow(RuntimeException::class);

    expect(User::count())->toBe(0)
        ->and(BuyerProfile::count())->toBe(0);
});

it('still creates the account even if sending the verification email fails', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('mail transport unavailable'));

    $payload = registerBuyerPayload();

    $result = app(RegisterBuyerAction::class)->execute(
        $payload['name'],
        $payload['email'],
        $payload['password'],
        $payload['company_name'],
        $payload['country_id'],
        $payload['phone'],
    );

    expect($result['user'])->not->toBeNull()
        ->and(User::count())->toBe(1)
        ->and(BuyerProfile::count())->toBe(1);
});
