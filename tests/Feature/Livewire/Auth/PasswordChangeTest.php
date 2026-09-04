<?php

use App\Actions\CreateAdminManagedUserAction;
use App\Enums\UserRole;
use App\Livewire\Auth\PasswordChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rejects the wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    Livewire::actingAs($user)
        ->test(PasswordChange::class)
        ->set('current_password', 'not-the-right-password')
        ->set('password', 'new-strong-password1')
        ->set('password_confirmation', 'new-strong-password1')
        ->call('update')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('correct-password', $user->refresh()->password))->toBeTrue();
});

it('rejects a mismatched confirmation', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    Livewire::actingAs($user)
        ->test(PasswordChange::class)
        ->set('current_password', 'correct-password')
        ->set('password', 'new-strong-password1')
        ->set('password_confirmation', 'does-not-match')
        ->call('update')
        ->assertHasErrors(['password']);
});

it('rejects a password that is too weak', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    Livewire::actingAs($user)
        ->test(PasswordChange::class)
        ->set('current_password', 'correct-password')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('update')
        ->assertHasErrors(['password']);
});

it('updates the password, clears must_change_password, and redirects home', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'must_change_password' => true,
    ]);

    Livewire::actingAs($user)
        ->test(PasswordChange::class)
        ->set('current_password', 'correct-password')
        ->set('password', 'new-strong-password1')
        ->set('password_confirmation', 'new-strong-password1')
        ->call('update')
        ->assertRedirect('/');

    $user->refresh();

    expect(Hash::check('new-strong-password1', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();
});

// --- end-to-end: real login -> forced gate -> Livewire change -> gate lifts --

it('lets an admin-created user complete the forced password change and reach the app afterward', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    $this->get('/')->assertRedirect(route('password.change'));

    Livewire::test(PasswordChange::class)
        ->set('current_password', $result['temporary_password'])
        ->set('password', 'new-strong-password1')
        ->set('password_confirmation', 'new-strong-password1')
        ->call('update')
        ->assertRedirect('/');

    // A vendor's "reaching the app" is now their inbox, not the generic
    // landing page -- see HomePageTest for the dedicated coverage of
    // that redirect itself.
    $this->get('/')->assertRedirect(route('vendor.inbox'));
});
