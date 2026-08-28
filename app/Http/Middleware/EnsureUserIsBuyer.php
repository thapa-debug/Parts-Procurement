<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level half of the /buyer portal's isolation guard (CLAUDE.md 4) --
 * mirrors EnsureUserIsAdmin. The other half is each buyer-only Livewire
 * component authorizing itself again on mount() via the relevant Policy.
 */
class EnsureUserIsBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isBuyer(), 403);

        return $next($request);
    }
}
