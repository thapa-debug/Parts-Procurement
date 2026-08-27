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

// --- must-change middleware is un-bypassable, proven through a real login --

it('redirects to the password-change page after a real login on a temporary password', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    $this->get('/')->assertRedirect(route('password.change'));
});

it('reaches an arbitrary route normally once logged in with a password that needs no change', function () {
    Route::get('/__test/arbitrary', fn () => 'reached')->middleware('web');

    $buyer = User::factory()->buyer()->create();

    $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->get('/__test/arbitrary')->assertOk()->assertSee('reached');
});

it('does not redirect loop on the password-change page itself after a real login', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    $this->get(route('password.change'))->assertOk();
});

it('rejects login with the wrong password and leaves the user a guest', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'not-the-right-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('leaves guests alone on routes that do not require auth', function () {
    Route::get('/__test/arbitrary-2', fn () => 'reached')->middleware('web');

    $this->get('/__test/arbitrary-2')->assertOk()->assertSee('reached');
});

it('does not redirect away Livewire\'s own shared update endpoint, or the password-change form would never be able to submit', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    // Matches the real route Livewire registers for all component AJAX
    // traffic (see Livewire\Mechanisms\HandleRequests\HandleRequests::boot).
    Route::post('/__test/livewire-update', fn () => 'reached')
        ->middleware('web')
        ->name('default.livewire.update');

    $this->post('/__test/livewire-update')->assertOk()->assertSee('reached');
});
