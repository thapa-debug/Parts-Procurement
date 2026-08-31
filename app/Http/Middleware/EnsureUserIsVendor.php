<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level half of the /vendor portal's isolation guard (CLAUDE.md 4) --
 * mirrors EnsureUserIsAdmin/EnsureUserIsBuyer. The other half is each
 * vendor-only Livewire component authorizing itself again on mount() via
 * the relevant Policy.
 */
class EnsureUserIsVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isVendor(), 403);

        return $next($request);
    }
}
