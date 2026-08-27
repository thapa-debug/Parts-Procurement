<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied globally to the `web` middleware group (see bootstrap/app.php) so no
 * route can opt out. A user flagged must_change_password (temporary password
 * from admin-created onboarding) is redirected everywhere except the
 * password-change page itself and logout, until they change it.
 *
 * Livewire's own AJAX traffic -- including the password-change form's
 * wire:submit -- is multiplexed through one shared, package-registered route
 * (`default.livewire.update`), not through `password.change` itself. That
 * route also runs the `web` group, so it must be exempted too, or the very
 * request meant to clear the flag gets redirected away before the
 * component's update() method ever runs. Since every other page's initial
 * GET is already blocked by this same gate, that shared endpoint can only
 * ever be carrying traffic for the password-change component while the flag
 * is set -- exempting it by name doesn't open up anything else.
 */
class EnsureMustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change', 'logout', 'default.livewire.update')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
