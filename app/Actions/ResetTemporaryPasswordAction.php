<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Recovery for a lost admin-issued temporary password (CreateAdminManagedUserAction):
 * generates a new one and forces another change-on-next-login. Role-agnostic,
 * like the action it mirrors -- works on any admin-created account, not just
 * vendors, even though the vendor master is the first caller.
 */
class ResetTemporaryPasswordAction
{
    /**
     * @return array{user: User, temporary_password: string}
     */
    public function execute(User $user): array
    {
        $temporaryPassword = Str::password(16);

        $user->update([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }
}
