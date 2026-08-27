<?php

namespace App\Actions;

use App\Models\User;

/**
 * Backs the forced password-change gate (must_change_password onboarding,
 * see EnsureMustChangePassword) and doubles as the primitive for any future
 * self-service "change my password" screen -- clears the flag either way.
 */
class ChangePasswordAction
{
    public function execute(User $user, string $newPassword): User
    {
        $user->update([
            'password' => $newPassword,
            'must_change_password' => false,
        ]);

        return $user;
    }
}
