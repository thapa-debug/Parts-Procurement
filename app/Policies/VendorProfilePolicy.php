<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorProfile;

class VendorProfilePolicy
{
    /**
     * Only the admin sees the vendor master list (CLAUDE.md 4: buyers must
     * never see vendor identity).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * The admin sees any vendor profile; a vendor sees only their own.
     */
    public function view(User $user, VendorProfile $vendorProfile): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $user->id === $vendorProfile->user_id);
    }

    /**
     * Vendor accounts are admin-created only (CLAUDE.md 14).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Vendor master edits and suspend/resume are admin-only actions.
     */
    public function update(User $user, VendorProfile $vendorProfile): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VendorProfile $vendorProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VendorProfile $vendorProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VendorProfile $vendorProfile): bool
    {
        return false;
    }
}
