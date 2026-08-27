<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the login page for a guest', function () {
    $this->get('/login')->assertOk()->assertSee(__('auth.login.heading'));
});

// --- rate limiting (LoginRequest::ensureIsNotRateLimited) -------------------

it('locks out after five failed attempts and blocks even the correct password', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many login attempts');

    $this->assertGuest();
});

it('rate-limits per email, not globally', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['email' => $other->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($other);
});

it('clears prior failed attempts once a login succeeds', function () {
    $user = User::factory()->create();

    foreach (range(1, 4) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->post('/logout');

    // If the 4 prior failures still counted, this single failure would already
    // be attempt 5 and trip the lockout -- it must not.
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->not->toContain('Too many login attempts');
});
