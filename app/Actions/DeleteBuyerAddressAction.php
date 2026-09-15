<?php

namespace App\Actions;

use App\Models\BuyerAddress;
use Illuminate\Support\Facades\DB;

/**
 * Deletes one of a buyer's saved addresses (CLAUDE.md §14 Phase 4 slice
 * 2). If the deleted address was the buyer's default, the most recently
 * created remaining address (if any) is promoted to default in the same
 * transaction -- "default" should never silently disappear while other
 * addresses still exist.
 *
 * part_requests' own shipping snapshot columns are unaffected by this:
 * shipping_address_id nulls out (nullOnDelete, see the migration) but the
 * flattened shipping_* columns -- the actual source of truth for an
 * already-placed order -- are untouched. See SnapshotShippingAddressAction.
 */
class DeleteBuyerAddressAction
{
    public function execute(BuyerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $buyerId = $address->buyer_id;

            $address->delete();

            if ($wasDefault) {
                BuyerAddress::query()
                    ->where('buyer_id', $buyerId)
                    ->latest('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
