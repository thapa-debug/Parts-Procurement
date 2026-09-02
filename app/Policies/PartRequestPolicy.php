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
     * Broadcasting a request to vendors (打診) is operational admin work --
     * today just `isAdmin()`, the same owner/staff permission slot as
     * viewBoard/create. The request's own eligibility (must still be
     * `new`) is a business rule BroadcastRequestAction enforces itself,
     * not a role check, so it doesn't belong here.
     */
    public function broadcast(User $user, PartRequest $partRequest): bool
    {
        return $user->isAdmin();
    }

    /**
     * The vendor inbox list -- any vendor may view their own inbox; scoping
     * to only the requests actually broadcast to them happens in the
     * inbox's own query, the same "coarse policy + query does the
     * narrowing" shape as viewAny above.
     */
    public function viewVendorInbox(User $user): bool
    {
        return $user->isVendor();
    }

    /**
     * A vendor may open or respond to ONE specific request only if it was
     * actually broadcast to them (request_vendor pivot). Unlike broadcast()
     * above, this can't be a coarse role check: showing an uninvited vendor
     * a request's detail page at all would leak that the request exists
     * and what's in it, even though nothing rendered there is "buyer
     * identity" per se. SubmitVendorResponseAction re-checks the same
     * eligibility itself -- defense-in-depth, the same shape as
     * BroadcastRequestAction re-checking vendor status.
     */
    public function respond(User $user, PartRequest $partRequest): bool
    {
        if (! $user->isVendor() || ! $user->vendorProfile) {
            return false;
        }

        return $partRequest->vendors()->where('vendor_profiles.id', $user->vendorProfile->id)->exists();
    }

    /**
     * Presenting a priced quote to the buyer (見積もり提示) is operational
     * admin work -- today just `isAdmin()`, the same owner/staff permission
     * slot as broadcast()/viewBoard()/create(). Which response is eligible
     * (must belong to this request, must not be a no-stock reply) and
     * whether the request itself is in the right status are business rules
     * PresentQuoteAction enforces itself, not role checks, so they don't
     * belong here.
     */
    public function presentQuote(User $user, PartRequest $partRequest): bool
    {
        return $user->isAdmin();
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
