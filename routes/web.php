<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Admin\BuyerDetail;
use App\Livewire\Admin\BuyerMaster;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\VendorDetail;
use App\Livewire\Admin\VendorMaster;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PasswordChange;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', Login::class)
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/register', Register::class)
    ->middleware('guest')
    ->name('register');

Route::get('/password/change', PasswordChange::class)
    ->middleware('auth')
    ->name('password.change');

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/vendors', VendorMaster::class)->name('vendors.index');
    Route::get('/vendors/{vendorProfile}', VendorDetail::class)->name('vendors.show');
    Route::get('/buyers', BuyerMaster::class)->name('buyers.index');
    Route::get('/buyers/{buyerProfile}', BuyerDetail::class)->name('buyers.show');
    Route::get('/settings', Settings::class)->name('settings');
});
