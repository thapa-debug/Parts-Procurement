<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Universal invariant (CLAUDE.md 14): email verification gates the
        // ability to act (buyer creating a request, vendor responding to an
        // inquiry) -- not the ability to log in. Apply to action routes/
        // Livewire components as they're built in Phase 2.
        Gate::define('act', fn (User $user): bool => $user->hasVerifiedEmail());
    }
}
