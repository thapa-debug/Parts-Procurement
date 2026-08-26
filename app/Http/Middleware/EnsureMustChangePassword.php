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
 */
class EnsureMustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
