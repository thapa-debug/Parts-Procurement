<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level half of the /admin portal's isolation guard (CLAUDE.md 4) --
 * the other half is each admin-only Livewire component authorizing itself
 * again on mount() via the relevant Policy, so a buyer/vendor is blocked
 * even if a route were ever misconfigured without this middleware.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
