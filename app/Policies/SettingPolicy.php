<?php

namespace App\Policies;

use App\Models\User;

/**
 * Settings is a singleton config screen, not a per-row resource -- no model
 * instance to check against, just the admin-only capability itself (called
 * class-based: $this->authorize('viewAny'|'update', Setting::class)).
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
