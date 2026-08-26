<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Placeholder until the real Livewire form is built (Phase 1 UI pass) — the
// EnsureMustChangePassword middleware needs a named route to redirect to.
Route::get('/password/change', function () {
    return 'Password change form placeholder.';
})->middleware('auth')->name('password.change');
