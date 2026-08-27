<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function verificationUrlFor(User $user): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );
}

// --- verifying via the signed link -----------------------------------------

it('marks the email verified when the signed link is valid and fires Verified', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrlFor($user))
        ->assertRedirect('/');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

it('rejects a tampered hash', function () {
    $user = User::factory()->unverified()->create();
    $otherUsersHash = sha1('someone-else@example.com');

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => $otherUsersHash],
    );

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired/invalid signature', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]))
        ->assertForbidden();
});

it('redirects a guest to login instead of erroring on the verify link', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrlFor($user))->assertRedirect('/login');
});

it('does not re-fire Verified for an already-verified user', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(verificationUrlFor($user))
        ->assertRedirect('/');

    Event::assertNotDispatched(Verified::class);
});

// --- resend ------------------------------------------------------------

it('resends the verification email for an unverified user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/email/verification-notification')
        ->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend for an already-verified user', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->post('/email/verification-notification');

    Notification::assertNothingSent();
});

it('redirects a guest posting to the resend endpoint to login', function () {
    $this->post('/email/verification-notification')->assertRedirect('/login');
});

// --- the verification banner --------------------------------------------

it('shows the verification banner for an unverified user and hides it once verified', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/')->assertSee(__('auth.verification.banner'));

    $this->actingAs($user)->get(verificationUrlFor($user));

    $this->actingAs($user->fresh())->get('/')->assertDontSee(__('auth.verification.banner'));
});

// --- the whole loop: the `act` gate before and after verification -----------

it('blocks the act gate before verification and allows it after the real signed link is clicked', function () {
    $user = User::factory()->unverified()->create();

    expect($user->can('act'))->toBeFalse();

    $this->actingAs($user)->get(verificationUrlFor($user))->assertRedirect('/');

    expect($user->fresh()->can('act'))->toBeTrue();
});
