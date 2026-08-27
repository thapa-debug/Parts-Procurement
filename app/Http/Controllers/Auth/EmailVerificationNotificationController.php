<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Backs the "resend" button on the verification banner (see the shared
     * layout). Redirects back to wherever the banner was shown, not to a
     * dedicated page -- CLAUDE.md's universal invariant is that verification
     * never blocks browsing, so there's no forced "check your email" page.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('auth.verification.sent'));
    }
}
