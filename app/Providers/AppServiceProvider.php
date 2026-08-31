<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Console\ServeCommand;
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
        $this->fixServeCommandTemporaryDirectoryOnWindows();

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

    /**
     * `php artisan serve` (with live-reload enabled -- the default, no
     * --no-reload) strips almost every environment variable from the PHP
     * built-in server process it spawns, passing through only a small
     * allow-list (`ServeCommand::$passthroughVariables`). TMP/TEMP aren't
     * on it. Without them, PHP's own temp-directory resolution -- used for
     * both rfc1867 multipart upload buffering and plain tmpfile() calls --
     * falls back to something that isn't writable in this environment on
     * Windows, so any file upload (e.g. the vendor quote-photo form) fails
     * with "unable to create a temporary file", which Livewire's own
     * client-side error handler then can't parse as JSON and crashes on
     * with "Trying to access array offset on null"
     * (WithFileUploads.php:93) -- confirmed by reproducing the same
     * failure with a raw multipart curl request straight to Livewire's
     * upload endpoint, with no app code involved at all.
     *
     * This is harmless to set unconditionally (it only has any effect
     * while `artisan serve` itself is running) and keeps live-reload
     * working, unlike the `--no-reload` flag workaround.
     */
    private function fixServeCommandTemporaryDirectoryOnWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        ServeCommand::$passthroughVariables = array_merge(
            ServeCommand::$passthroughVariables,
            ['TMP', 'TEMP', 'TMPDIR'],
        );
    }
}
