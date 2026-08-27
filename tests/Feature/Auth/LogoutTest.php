<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs an authenticated user out and redirects home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

it('redirects a guest posting to /logout to the login page instead of erroring', function () {
    $this->post('/logout')->assertRedirect('/login');
});

it('shows the logged-in user\'s name and a logout control in the header', function () {
    $user = User::factory()->create(['name' => 'Jane Buyer']);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Jane Buyer')
        ->assertSee(__('app.logout'));
});

it('hides the account/logout header for a guest', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee(__('app.logout'));
});
