<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Placeholder until the real Livewire form is built (Phase 1 UI pass) — the
// EnsureMustChangePassword middleware needs a named route to redirect to.
// Not guarded by `auth` yet: that middleware calls route('login'), which
// doesn't exist until the login screen ships, and would 500 for a guest.
Route::get('/password/change', function () {
    return 'Password change form placeholder.';
})->name('password.change');
