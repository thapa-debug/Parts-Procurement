<?php

namespace App\Actions;

use App\Models\BuyerProfile;
use App\Models\User;

/**
 * Approves a self-registered buyer (CLAUDE.md §14) so they can pass the
 * `act` gate and start creating requests. Admin-created buyers never need
 * this -- CreateBuyerAction approves them at creation time, since the admin
 * already vetted the account by creating it directly.
 */
class ApproveBuyerAction
{
    public function execute(BuyerProfile $buyerProfile, User $admin): BuyerProfile
    {
        $buyerProfile->update([
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        return $buyerProfile->fresh();
    }
}
