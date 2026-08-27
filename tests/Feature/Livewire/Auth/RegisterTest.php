<?php

use App\Livewire\Auth\Register;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function fillValidRegisterForm($component)
{
    return $component
        ->set('name', 'Jane Buyer')
        ->set('email', 'jane@example.com')
        ->set('password', 'my-strong-password1')
        ->set('password_confirmation', 'my-strong-password1')
        ->set('company_name', 'Acme Imports')
        ->set('phone', '090-0000-0000')
        ->set('default_destination_country', 'Australia')
        ->set('default_yard', 'Oceania Yard');
}

it('renders the registration page for a guest', function () {
    $this->get('/register')->assertOk()->assertSee(__('auth.register.heading'));
});

it('registers a buyer, logs them in immediately, and sends a verification email', function () {
    Notification::fake();

    fillValidRegisterForm(Livewire::test(Register::class))
        ->call('register')
        ->assertRedirect('/');

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->must_change_password)->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(BuyerProfile::where('user_id', $user->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects an incomplete registration form', function () {
    Livewire::test(Register::class)
        ->call('register')
        ->assertHasErrors([
            'name', 'email', 'password', 'company_name', 'phone',
            'default_destination_country', 'default_yard',
        ]);

    expect(User::count())->toBe(0);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    fillValidRegisterForm(Livewire::test(Register::class))
        ->set('email', 'taken@example.com')
        ->call('register')
        ->assertHasErrors(['email']);
});

it('rejects a mismatched password confirmation', function () {
    fillValidRegisterForm(Livewire::test(Register::class))
        ->set('password_confirmation', 'does-not-match')
        ->call('register')
        ->assertHasErrors(['password']);
});

it('rejects a weak password', function () {
    fillValidRegisterForm(Livewire::test(Register::class))
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('register')
        ->assertHasErrors(['password']);
});

// --- end-to-end: real registration -> immediate login -> unverified banner --

it('lets a newly registered buyer reach the app immediately and see the verification banner', function () {
    Notification::fake();

    fillValidRegisterForm(Livewire::test(Register::class))
        ->call('register')
        ->assertRedirect('/');

    $this->get('/')
        ->assertOk()
        ->assertSee(__('auth.verification.banner'));
});
