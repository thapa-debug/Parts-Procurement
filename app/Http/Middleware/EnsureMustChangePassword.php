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
 *
 * `livewire.upload-file` and `livewire.preview-file` need the same
 * exemption, for a subtler reason: a `WithFileUploads` component (e.g.
 * vendor quote photos) uploads via a *separate* request to
 * `livewire/upload-file`, ahead of and independent from the component's own
 * `default.livewire.update` traffic. If that upload request gets redirected
 * here instead of returning Livewire's expected JSON body, the browser's
 * Livewire JS treats the redirect body as an upload error and replays it
 * through the component's `_uploadErrored()` method on the *next*
 * `default.livewire.update` call -- which then 500s trying to
 * `json_decode()` a redirect response as JSON (Livewire's own
 * WithFileUploads.php, not a bug in this app's code, but one only visible
 * once this middleware blocks the upload). `livewire.preview-file` (the
 * temporary-upload thumbnail preview `<img>` src) gets the same treatment
 * for symmetry, even though a blocked preview would only break an image,
 * not crash the page.
 */
class EnsureMustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change', 'logout', 'default.livewire.update', 'livewire.upload-file', 'livewire.preview-file')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
