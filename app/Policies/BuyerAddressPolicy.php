<?php

namespace App\Policies;

use App\Models\BuyerAddress;
use App\Models\User;

/**
 * A buyer's own saved addresses -- buyer AND owner only, no admin bypass,
 * same shape as PartRequestPolicy::viewOwn/selectQuote. Deliberately no
 * admin access: this is the buyer's own address book, distinct from the
 * immutable shipping snapshot admin sees on a paid part_request once
 * checkout exists (Slice 3) -- an admin never needs to browse or edit it
 * directly. Revisit if that turns out wrong.
 */
class BuyerAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBuyer();
    }

    public function view(User $user, BuyerAddress $buyerAddress): bool
    {
        return $this->owns($user, $buyerAddress);
    }

    public function create(User $user): bool
    {
        return $user->isBuyer();
    }

    public function update(User $user, BuyerAddress $buyerAddress): bool
    {
        return $this->owns($user, $buyerAddress);
    }

    public function delete(User $user, BuyerAddress $buyerAddress): bool
    {
        return $this->owns($user, $buyerAddress);
    }

    private function owns(User $user, BuyerAddress $buyerAddress): bool
    {
        return $user->isBuyer() && $user->buyerProfile?->id === $buyerAddress->buyer_id;
    }
}
