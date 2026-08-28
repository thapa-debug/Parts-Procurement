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
        // inquiry) -- not the ability to log in.
        //
        // Buyers carry one extra condition on top: a self-registered buyer
        // also needs admin approval (CLAUDE.md 14's new buyer-approval
        // gate) before they can create a request. Admin-created buyers are
        // approved at creation (CreateBuyerAction), so this only ever holds
        // up a self-registered buyer between verifying and being approved.
        // Vendors/admins are unaffected -- verification alone is enough.
        Gate::define('act', function (User $user): bool {
            if (! $user->hasVerifiedEmail()) {
                return false;
            }

            if ($user->isBuyer()) {
                return (bool) $user->buyerProfile?->isApproved();
            }

            return true;
        });
    }
}
