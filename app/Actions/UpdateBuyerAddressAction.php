<?php

namespace App\Actions;

use App\Models\BuyerAddress;
use Illuminate\Support\Facades\DB;

/**
 * Edits one of a buyer's saved addresses in place (CLAUDE.md §14 Phase 4
 * slice 2). If this edit marks it default, every other of the same
 * buyer's addresses is unset in the same transaction -- the same "one
 * default at a time" invariant as CreateBuyerAddressAction. Deliberately
 * one-directional: this never turns an address FROM default TO not-
 * default on its own (an omitted or false is_default is simply ignored
 * when the address is already the default), so the invariant "always
 * exactly one default once any address exists" never has a gap. Default
 * only ever moves to a *different* address -- via a later Create/Update
 * that marks that other one default, via SetDefaultBuyerAddressAction, or
 * via DeleteBuyerAddressAction promoting a replacement.
 */
class UpdateBuyerAddressAction
{
    /**
     * @param  array{recipient_name: string, phone: string, postal_code: string, country_id: int, state?: string|null, city: string, address_line1: string, address_line2?: string|null, is_default?: bool}  $data
     */
    public function execute(BuyerAddress $address, array $data): BuyerAddress
    {
        return DB::transaction(function () use ($address, $data) {
            $requestedDefault = (bool) ($data['is_default'] ?? false);

            if ($requestedDefault && ! $address->is_default) {
                $address->buyer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update([
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'postal_code' => $data['postal_code'],
                'country_id' => $data['country_id'],
                'state' => $data['state'] ?? null,
                'city' => $data['city'],
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'is_default' => $address->is_default || $requestedDefault,
            ]);

            return $address->fresh();
        });
    }
}
