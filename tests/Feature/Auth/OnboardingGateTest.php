<?php

use App\Actions\CreateAdminManagedUserAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// --- unverified account blocked from acting ---------------------------------

it('blocks an unverified user from acting', function () {
    $user = User::factory()->unverified()->create();

    expect(Gate::forUser($user)->allows('act'))->toBeFalse();
});

it('allows a verified user to act', function () {
    $user = User::factory()->create();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(Gate::forUser($user)->allows('act'))->toBeTrue();
});

// --- temp-password forces change --------------------------------------------

it('creates admin-managed accounts with a temporary password that forces a change', function (UserRole $role) {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Doe', 'jane@example.com', $role);

    $user = $result['user'];
    $temporaryPassword = $result['temporary_password'];

    expect($user->role)->toBe($role)
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(strlen($temporaryPassword))->toBeGreaterThanOrEqual(16)
        ->and(Hash::check($temporaryPassword, $user->password))->toBeTrue()
        ->and($user->password)->not->toBe($temporaryPassword);
})->with([
    'buyer' => [UserRole::Buyer],
    'vendor' => [UserRole::Vendor],
]);

it('does not force a password change for a self-registered buyer', function () {
    $buyer = User::factory()->buyer()->create();

    expect($buyer->must_change_password)->toBeFalse();
});

// --- must-change middleware is un-bypassable --------------------------------

it('redirects any route to the password-change page while must_change_password is true', function () {
    Route::get('/__test/arbitrary', fn () => 'reached')->middleware('web');

    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get('/__test/arbitrary')
        ->assertRedirect(route('password.change'));
});

it('does not redirect once must_change_password is false', function () {
    Route::get('/__test/arbitrary-2', fn () => 'reached')->middleware('web');

    $user = User::factory()->create(['must_change_password' => false]);

    $this->actingAs($user)
        ->get('/__test/arbitrary-2')
        ->assertOk()
        ->assertSee('reached');
});

it('does not redirect loop on the password-change page itself', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertOk();
});

it('leaves guests alone', function () {
    Route::get('/__test/arbitrary-3', fn () => 'reached')->middleware('web');

    $this->get('/__test/arbitrary-3')->assertOk()->assertSee('reached');
});
