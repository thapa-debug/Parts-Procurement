<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Renders the login card only -- submission is a plain HTML form POSTing to
 * the tested AuthenticatedSessionController@store / LoginRequest pair
 * (rate limiting included), not a wire:submit handler. Duplicating that
 * authentication logic here would risk the two paths drifting apart on a
 * security-critical flow.
 */
class Login extends Component
{
    public function render(): View
    {
        return view('livewire.auth.login')->title(__('auth.login.title'));
    }
}
