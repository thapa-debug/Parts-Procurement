<?php

namespace App\Policies;

use App\Models\PartRequest;
use App\Models\User;

class PartRequestPolicy
{
    /**
     * The admin board (any admin, today just `isAdmin()` -- this is the
     * slot CLAUDE.md §4's owner/staff permission split layers into later,
     * since requests/quotes are operational work both tiers do) and a
     * buyer's own "my requests" list both go through this same check;
     * scoping to "own only" happens in the buyer's list query, not here.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isBuyer();
    }

    /**
     * The admin request board specifically -- distinct from viewAny, which
     * also permits a buyer to see their own future "my requests" list. The
     * board itself is admin-only, full stop (today just `isAdmin()`, the
     * same owner/staff permission slot as everywhere else in `/admin`).
     */
    public function viewBoard(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * The admin sees any request; a buyer sees only their own. A vendor
     * never sees a part_request directly through this policy -- CLAUDE.md
     * §4 isolation, and the (held-back) vendor-inquiry workflow will expose
     * only a deliberately narrow, buyer-identity-free view, not this model.
     */
    public function view(User $user, PartRequest $partRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isBuyer() && $user->buyerProfile?->id === $partRequest->buyer_id;
    }

    /**
     * Structural check only: is this account shaped like something that's
     * ever allowed to submit a request. The CLAUDE.md §14 "act" gate
     * (verified + approved) is a separate, additional check the caller
     * makes via `authorize('act')` per CONVENTIONS.md -- not folded in here,
     * so this policy answers "a buyer" and the gate answers "this buyer,
     * right now".
     */
    public function create(User $user): bool
    {
        return $user->isBuyer();
    }

    /**
     * No direct field-level edits are planned -- every status transition
     * is owned by a guarded Action (CLAUDE.md §5), not a raw model update.
     * Defined (false) for completeness/consistency with the other policies
     * rather than left unspecified.
     */
    public function update(User $user, PartRequest $partRequest): bool
    {
        return false;
    }

    public function delete(User $user, PartRequest $partRequest): bool
    {
        return false;
    }

    public function restore(User $user, PartRequest $partRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, PartRequest $partRequest): bool
    {
        return false;
    }
}
