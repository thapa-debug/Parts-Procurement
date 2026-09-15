<?php

namespace App\Actions;

use App\Models\BuyerAddress;
use Illuminate\Support\Facades\DB;

/**
 * Explicitly marks one of a buyer's addresses as their default, unsetting
 * every other one of that buyer's addresses in the same transaction
 * (CLAUDE.md §14 Phase 4 slice 2). A dedicated action, separate from
 * UpdateBuyerAddressAction, for a one-click "set as default" control that
 * shouldn't need the full edit form.
 */
class SetDefaultBuyerAddressAction
{
    public function execute(BuyerAddress $address): BuyerAddress
    {
        return DB::transaction(function () use ($address) {
            $address->buyer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }
}
