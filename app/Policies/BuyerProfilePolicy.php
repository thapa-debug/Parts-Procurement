<?php

namespace App\Policies;

use App\Models\BuyerProfile;
use App\Models\User;

class BuyerProfilePolicy
{
    /**
     * Only the admin sees the buyer master list (CLAUDE.md 4: vendors must
     * never see buyer identity).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * The admin sees any buyer profile; a buyer sees only their own.
     */
    public function view(User $user, BuyerProfile $buyerProfile): bool
    {
        return $user->isAdmin() || ($user->isBuyer() && $user->id === $buyerProfile->user_id);
    }

    /**
     * Governs the admin-created path only (CreateBuyerAction). Self-
     * registration has no authenticated actor to check against -- it's a
     * public route, not policy-gated.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Buyer master edits are admin-only actions.
     */
    public function update(User $user, BuyerProfile $buyerProfile): bool
    {
        return $user->isAdmin();
    }

    /**
     * Approving a pending self-registered buyer (CLAUDE.md §14) is
     * operational admin work -- today just `isAdmin()`, the same slot
     * CLAUDE.md §4's owner/staff permission split layers into later.
     */
    public function approve(User $user, BuyerProfile $buyerProfile): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, BuyerProfile $buyerProfile): bool
    {
        return false;
    }

    public function restore(User $user, BuyerProfile $buyerProfile): bool
    {
        return false;
    }

    public function forceDelete(User $user, BuyerProfile $buyerProfile): bool
    {
        return false;
    }
}
