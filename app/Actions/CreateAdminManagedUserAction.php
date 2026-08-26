<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared onboarding mechanism for admin-created buyer/vendor accounts
 * (CLAUDE.md 14): a temporary password the admin relays out-of-band, forcing
 * a change on first login. Never emailed -- the account simply waits until
 * the admin can reach the buyer/vendor.
 */
class CreateAdminManagedUserAction
{
    /**
     * @return array{user: User, temporary_password: string}
     */
    public function execute(string $name, string $email, UserRole $role): array
    {
        $temporaryPassword = Str::password(16);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $temporaryPassword,
            'role' => $role,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }
}
